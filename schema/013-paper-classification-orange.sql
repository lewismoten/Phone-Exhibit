INSERT INTO directory_paper_classifications (
    code,
    label,
    description,
    color_hex,
    sort_order,
    is_active
) VALUES
('O', 'Orange', 'Arts, stories, and entertainment', '#efc48a', 50, 1)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description),
    color_hex = VALUES(color_hex),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

UPDATE directory_paper_classifications
SET sort_order = 60
WHERE code = 'P';
