<link rel="stylesheet" href="<?= base_url('vendor/datatables/dataTables.bootstrap5.min.css') ?>">
<style>
    /* Deep Navy & Coral — DataTables skin */
    table.dataTable thead th {
        border-bottom: 1px solid var(--s3-line) !important;
    }
    table.dataTable.no-footer {
        border-bottom: 1px solid var(--s3-line) !important;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: var(--s3-coral);
        outline: none;
        box-shadow: 0 0 0 3px rgba(232, 93, 76, 0.16);
    }
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid var(--s3-line);
        border-radius: var(--radius-sm);
        padding: 0.25rem 0.5rem;
    }
</style>
