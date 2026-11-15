<?php
/**
 * Controller for frontend/gallery.php
 */

/**
 * Partition gallery frames into rhythmic rows of 2 (span 3 of 6 cols) and 3 (span 2 of 6 cols)
 * so every desktop row is balanced edge-to-edge without single-card orphan slots.
 */
function build_editorial_spans(int $count): array
{
    if ($count <= 0) {
        return [];
    }
    if ($count === 1) {
        return [6];
    }

    $spans = [];
    $remaining = $count;
    $preferThree = false;

    while ($remaining > 0) {
        if ($remaining === 4) {
            $take = 2;
        } elseif ($remaining === 3) {
            $take = 3;
        } elseif ($remaining === 2) {
            $take = 2;
        } else {
            $take = $preferThree ? 3 : 2;
            if ($remaining - $take === 1) {
                $take = ($take === 3) ? 2 : 3;
            }
        }

        $colSpan = ($take === 2) ? 3 : 2;
        for ($k = 0; $k < $take; $k++) {
            $spans[] = $colSpan;
        }
        $remaining -= $take;
        $preferThree = !$preferThree;
    }

    return $spans;
}

function sfbc_handle_gallery_page(mysqli $conn): array
{
    header('Cache-Control: no-store');

    $photosRes = $conn->query("SELECT id, file_name, created_at FROM gallery ORDER BY created_at DESC");
    $photoItems = $photosRes ? $photosRes->fetch_all(MYSQLI_ASSOC) : [];
    $photoTotal = count($photoItems);

    $editorialSpans = build_editorial_spans($photoTotal);
    $countLabel = $photoTotal === 1 ? '1 Photograph' : ($photoTotal . ' Photographs');

    return compact('photoItems', 'photoTotal', 'editorialSpans', 'countLabel');
}
