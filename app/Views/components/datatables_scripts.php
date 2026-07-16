<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
(function ($) {
    if (! $.fn.dataTable) {
        return;
    }
    $.extend(true, $.fn.dataTable.defaults, {
        // Inline language — hindari race condition dari language.url (CDN async)
        // yang sering membuat tombol paginate/length tidak bisa diklik.
        language: {
            decimal: ',',
            thousands: '.',
            emptyTable: 'Tidak ada data pada tabel',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Menampilkan 0 sampai 0 dari 0 data',
            infoFiltered: '(disaring dari _MAX_ total data)',
            infoPostFix: '',
            lengthMenu: 'Tampilkan _MENU_ data',
            loadingRecords: 'Memuat...',
            processing: 'Memproses...',
            search: 'Cari:',
            zeroRecords: 'Tidak ditemukan data yang cocok',
            paginate: {
                first: 'Pertama',
                last: 'Terakhir',
                next: 'Berikutnya',
                previous: 'Sebelumnya'
            },
            aria: {
                sortAscending: ': aktifkan untuk mengurutkan naik',
                sortDescending: ': aktifkan untuk mengurutkan turun'
            }
        },
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Semua']],
        autoWidth: false,
        // Jangan set responsive:true tanpa plugin DataTables Responsive.
    });
})(jQuery);
</script>
