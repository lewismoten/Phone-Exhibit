onReady(async () => {
    const container = document.getElementById('phone-directory-results');

    if (!container) {
        return;
    }

    container.innerHTML = '<p>Loading directory…</p>';

    const result = await api('phone-directory');

    if (!result.success) {
        container.innerHTML = `<div class="error">${escape_html(result.error || 'Unable to load phone directory.')}</div>`;
        return;
    }

    const entries = Array.isArray(result.entries) ? result.entries : [];

    if (!entries.length) {
        container.innerHTML = '<p>No directory listings available.</p>';
        return;
    }

    const groups = group_directory_entries(entries);

    container.innerHTML = groups.map(render_directory_group).join('');
});

function group_directory_entries(entries) {
    const groups = [];
    const map = new Map();

    entries.forEach(entry => {
        const code = entry.paper_classification_code || '';
        const key = code || 'unclassified';

        if (!map.has(key)) {
            const group = {
                key,
                code,
                label: entry.paper_classification_label || 'Unclassified',
                color: entry.paper_classification_color || '',
                description: entry.paper_classification_description || 'Listings without a paper classification.',
                sortOrder: Number(entry.paper_classification_sort_order ?? 9999),
                entries: []
            };

            map.set(key, group);
            groups.push(group);
        }

        map.get(key).entries.push(entry);
    });

    groups.sort((a, b) => {
        if (a.sortOrder !== b.sortOrder) {
            return a.sortOrder - b.sortOrder;
        }

        return a.label.localeCompare(b.label);
    });

    return groups;
}

function render_directory_group(group) {
    const title = group.code
        ? `${group.label} Pages`
        : group.label;
    const tooltip = escape_html(group.description || title);
    const swatch = group.color
        ? `<span class="audio-file-classification-swatch" style="background:${escape_html(group.color)};"></span>`
        : '';

    return `
        <section class="phone-directory-group">
            <h2 class="phone-directory-group-title" title="${tooltip}">
                ${swatch}
                <span>${escape_html(title)}</span>
            </h2>
            <div class="phone-directory-table-wrap">
                <table class="phone-directory-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Phone</th>
                            <th>TTY</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${group.entries.map(render_directory_row).join('')}
                    </tbody>
                </table>
            </div>
        </section>
    `;
}

function render_directory_row(entry) {
    return `
        <tr>
            <td>${escape_html(entry.title || 'Untitled listing')}</td>
            <td>${escape_html(format_phone_number(entry.phone_number || ''))}</td>
            <td>${escape_html(entry.tty_phone_number ? format_phone_number(entry.tty_phone_number) : '—')}</td>
        </tr>
    `;
}
