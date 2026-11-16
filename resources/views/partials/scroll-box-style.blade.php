<style>
    /* more than 5 rows -> the list gets its own scrollbar (about 5 rows tall) */
    .scroll-box { max-height: 330px; overflow-y: auto; }
    .scroll-box thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
    .scroll-box::-webkit-scrollbar { width: 8px; }
    .scroll-box::-webkit-scrollbar-thumb { background: #c5cfe3; border-radius: 8px; }
</style>
