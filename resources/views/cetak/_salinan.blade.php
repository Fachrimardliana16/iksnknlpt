{{--
    Tanda air dokumen batal (checklist F8-09).

    Variabelnya opsional: `$salinan ?? false`. Halaman detail pesanan
    menampilkan komponen-komponen cetak sebagai pratinjau, dan di sana
    variabel ini tidak dikirim — sehingga tanpa nilai cadangan, Blade melempar
    galat dan seluruh halaman detail ikut gagal.

    Dipasang di badan dokumen, bukan sebagai lapisan melayang: dompdf tidak
    mendukung `position: fixed` lintas halaman maupun `transform`, jadi lapisan
    melayang akan tercetak di halaman pertama saja atau miring ke tempat yang
    salah. Pita merah di atas dan cakram abu-abu besar di tengah cukup untuk
    membuat dokumen tidak terbaca sebagai dokumen sah, dan keduanya tetap berada
    di aliran dokumen sehingga selalu ikut tercetak.
--}}
@if ($salinan ?? false)
    <div style="border:2px solid #b42318;color:#b42318;padding:6px 10px;margin:0 0 10px;font-size:11px;font-weight:700;letter-spacing:.08em;text-align:center">
        SALINAN &middot; PESANAN INI SUDAH DIBATALKAN
    </div>
    <div style="position:absolute;top:45%;left:0;width:100%;text-align:center;font-size:52pt;font-weight:700;color:#d4d4d8;letter-spacing:.12em">
        SALINAN
    </div>
@endif
