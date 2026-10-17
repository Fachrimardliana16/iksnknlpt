{{-- Livewire menuntut satu elemen akar. Tanpa pembungkus, cabang @if yang kosong
     membuat komponen gagal dirender dengan "missing root tag". --}}
<span>
    @if ($jumlah > 0)
        <span class="dot-count">{{ $jumlah > 99 ? '99+' : $jumlah }}</span>
    @endif
</span>
