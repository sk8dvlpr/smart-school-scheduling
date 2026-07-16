<script>
(function () {
    function syncCard($card) {
        const mode = $card.find('.pref-mode:checked').val() || 'none';
        const hasSlot = $card.find('.pref-slot').toArray().some(el => el.value === 'prefer' || el.value === 'avoid');
        const $badge = $card.find('.pref-badge');
        const $bobot = $card.find('.pref-bobot-wrap');
        if (mode === 'prefer') {
            $badge.text('Suka').removeClass('bg-light text-muted bg-danger').addClass('bg-success text-white');
        } else if (mode === 'avoid') {
            $badge.text('Hindari').removeClass('bg-light text-muted bg-success').addClass('bg-danger text-white');
        } else {
            $badge.text('Netral').removeClass('bg-success bg-danger text-white').addClass('bg-light text-muted');
        }
        if (mode === 'none' && !hasSlot) {
            $bobot.addClass('d-none');
        } else {
            $bobot.removeClass('d-none');
        }
    }
    $(document).on('change', '.pref-mode, .pref-slot', function () {
        syncCard($(this).closest('.pref-day-card'));
    });
    $('.pref-day-card').each(function () { syncCard($(this)); });
})();
</script>
