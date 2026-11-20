<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Company details, logo and bank details printed on every invoice.
 * The logo is sent as multipart/form-data (field "logo"), so the update is a POST (a multipart PUT is not read by PHP).
 */
class SettingController extends ApiController
{
    private const KEYS = [
        'company_name', 'company_tagline', 'company_office', 'company_address', 'company_email', 'company_website', 'company_phone',
        'invoice_subtitle', 'product_name', 'product_description', 'tax_text',
        'payment_method', 'bank_name', 'account_name', 'account_no', 'bank_branch', 'invoice_notes', 'invoice_footer',
    ];

    private function current(): array
    {
        $logo = Setting::get('logo_path');

        return Setting::many(self::KEYS) + ['logo_url' => $logo && is_file(public_path($logo)) ? asset($logo) : null];
    }

    public function show()
    {
        return $this->ok($this->current());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name'        => 'required|string|max:100',
            'company_tagline'     => 'nullable|string|max:150',
            'company_office'      => 'nullable|string|max:60',
            'company_address'     => 'nullable|string|max:300',
            'company_email'       => 'nullable|email|max:120',
            'company_website'     => 'nullable|string|max:120',
            'company_phone'       => 'nullable|string|max:40',
            'invoice_subtitle'    => 'nullable|string|max:60',
            'product_name'        => 'nullable|string|max:100',
            'product_description' => 'nullable|string|max:150',
            'tax_text'            => 'nullable|string|max:100',
            'payment_method'      => 'nullable|string|max:150',
            'bank_name'           => 'nullable|string|max:100',
            'account_name'        => 'nullable|string|max:100',
            'account_no'          => 'nullable|string|max:60',
            'bank_branch'         => 'nullable|string|max:100',
            'invoice_notes'       => 'nullable|string|max:1000',
            'invoice_footer'      => 'nullable|string|max:200',
            'logo'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_logo'         => 'nullable|boolean',
        ]);

        foreach (self::KEYS as $key) {
            Setting::put($key, $data[$key] ?? null);
        }

        $old = Setting::get('logo_path');
        if ($request->boolean('remove_logo') && $old) {
            $this->deleteLogo($old);
            Setting::put('logo_path', null);
            $old = null;
        }
        if ($request->hasFile('logo')) {
            if ($old) {
                $this->deleteLogo($old);
            }
            Setting::put('logo_path', $this->storeLogo($request->file('logo')));
        }

        return $this->ok($this->current(), 'Settings saved successfully. New invoices and downloads use them.');
    }

    /** Saves the logo in /public/uploads/branding, made smaller when the server can (keeps the PDF and the emails light). */
    private function storeLogo(UploadedFile $file): string
    {
        $dir = public_path('uploads/branding');
        File::ensureDirectoryExists($dir);

        $ext  = strtolower($file->extension() ?: 'png');
        $name = 'logo-' . Str::random(10) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $tmp  = $file->getRealPath();
        $done = false;

        if (function_exists('imagecreatetruecolor') && in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            [$w, $h] = getimagesize($tmp);
            $src = $ext === 'png' ? @imagecreatefrompng($tmp) : @imagecreatefromjpeg($tmp);

            if ($src && max($w, $h) > 480) {
                $scale = 480 / max($w, $h);
                $nw = (int) round($w * $scale);
                $nh = (int) round($h * $scale);
                $dst = imagecreatetruecolor($nw, $nh);
                if ($ext === 'png') {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                }
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                $ext === 'png' ? imagepng($dst, "$dir/$name") : imagejpeg($dst, "$dir/$name", 88);
                imagedestroy($dst);
                $done = true;
            }
            if ($src) {
                imagedestroy($src);
            }
        }

        if (! $done) {
            $file->move($dir, $name);
        }

        return 'uploads/branding/' . $name;
    }

    /** Only logos that were uploaded here are deleted (the shipped default stays). */
    private function deleteLogo(string $path): void
    {
        if (Str::startsWith(basename($path), 'logo-')) {
            File::delete(public_path($path));
        }
    }
}
