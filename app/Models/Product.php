<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Product extends Model
{
    use BelongsToAccount, SoftDeletes;

    protected $fillable = [
        'account_id',
        'user_id',
        'item_type',
        'category_id',
        'subcategory_id',
        'warranty_mode',
        'document_type',
        'issued_by',
        'name',
        'brand',
        'model',
        'serial_number',
        'description',
        'purchase_date',
        'purchase_price',
        'currency',
        'seller_name',
        'seller_phone',
        'invoice_number',
        'purchase_location',
        'notes',
        'is_active',
        'renewed_from_id',
    ];

    protected $appends = ['status'];

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'purchase_price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }
    public function warranties()
    {
        return $this->hasMany(Warranty::class)->orderBy('end_date');
    }
    public function documents()
    {
        return $this->hasMany(Document::class)->latest();
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function renewedFrom()
    {
        return $this->belongsTo(Product::class, 'renewed_from_id');
    }
    public function renewedTo()
    {
        return $this->hasOne(Product::class, 'renewed_from_id');
    }

    public function isDocument(): bool
    {
        return $this->item_type === 'document';
    }

    /** Earlier versions of a renewed document, oldest first (this record itself is not included). */
    public function renewalHistory(): Collection
    {
        $chain = collect();
        $current = $this;

        while ($current->renewed_from_id) {
            $previous = $current->renewedFrom()->with('warranties')->first();
            if (! $previous) break;
            $chain->prepend($previous);
            $current = $previous;
        }

        return $chain;
    }

    /** Overall status, based on all warranties / the validity period. */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active) return 'inactive';

        $w = $this->warranties;
        if ($w->isEmpty()) return 'none';

        $live = $w->filter(fn($x) => in_array($x->status, ['active', 'expiring']));
        if ($live->isEmpty()) {
            return $w->contains(fn($x) => $x->status === 'upcoming') ? 'upcoming' : 'expired';
        }
        return $live->contains(fn($x) => $x->status === 'active') ? 'active' : 'expiring';
    }

    public function scopeSearch($q, ?string $term)
    {
        if (! $term) return $q;
        return $q->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%")
                ->orWhere('document_type', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%");
        });
    }

    public function scopeStatus($q, ?string $status)
    {
        $today = today()->toDateString();
        $soon  = today()->addDays(config('warranty.expiring_days'))->toDateString();

        return match ($status) {
            'inactive' => $q->where('is_active', false),
            'active'   => $q->where('is_active', true)
                ->whereHas('warranties', fn($w) => $w->currentlyValid()),
            'expiring' => $q->where('is_active', true)
                ->whereHas('warranties', fn($w) => $w->whereBetween('end_date', [$today, $soon]))
                ->whereDoesntHave('warranties', fn($w) => $w->where('end_date', '>', $soon)),
            'expired'  => $q->where('is_active', true)
                ->whereDoesntHave('warranties', fn($w) => $w->where('end_date', '>=', $today)),
            default    => $q,
        };
    }
}
