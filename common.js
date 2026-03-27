document.addEventListener('DOMContentLoaded', function () {
    const times = document.querySelectorAll('.local-datetime');

    times.forEach((el) => {
        const utcValue = el.dataset.utc;
        if (!utcValue) return;

        const date = new Date(utcValue);
        if (Number.isNaN(date.getTime())) return;

        const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        const formatted = new Intl.DateTimeFormat(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            timeZoneName: 'short'
        }).format(date);

        el.textContent = formatted;

        const label = el.parentElement.querySelector('.local-timezone-label');
        if (label && timeZone) {
            label.textContent = `(${timeZone}, your local time)`;
        }
    });
});
