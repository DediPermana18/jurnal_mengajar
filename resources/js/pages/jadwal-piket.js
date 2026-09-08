export function initJadwalPiket() {
    const updateCounter = () => {
        const checkboxes = document.querySelectorAll('.guru-checkbox:checked');
        const badge = document.getElementById('badgeCounter');
        if (badge) {
            badge.textContent = `${checkboxes.length} Guru Dipilih`;
        }
        document.querySelectorAll('.guru-checkbox').forEach((checkbox) => {
            checkbox.closest('.guru-checkbox-card')?.classList.toggle('selected', checkbox.checked);
        });
    };

    window.toggleCardCheck = (card, event) => {
        if (event.target.tagName.toLowerCase() !== 'input') {
            const checkbox = card.querySelector('.guru-checkbox');
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
            }
        }
        updateCounter();
    };

    window.updateCounter = updateCounter;
    window.toggleSelectAll = (select) => {
        document.querySelectorAll('.guru-checkbox').forEach((checkbox) => {
            const item = checkbox.closest('.guru-item-col');
            if (item && !item.hidden) {
                checkbox.checked = select;
            }
        });
        updateCounter();
    };

    updateCounter();
}