<?php
$dh = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<section class="discount-card discount-table">
    <table>
        <thead>
            <tr>
                <th>Booking</th>
                <th>Guest &amp; Type</th>
                <th>Status</th>
                <th>Hold Deadline</th>
                <th style="text-align:right;">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($discountData['listRows'])): ?>
            <tr><td colspan="5" style="text-align:center; padding:32px; color:var(--text-muted);">No discount requests yet.</td></tr>
        <?php else: ?>
            <?php foreach ($discountData['listRows'] as $r):
                $st = sfbc_discount_status_class((string)$r['state']);
            ?>
            <tr>
                <td><strong>REF-<?php echo (int)$r['booking_id']; ?></strong></td>
                <td>
                    <div style="font-weight:600; color:var(--text-main);"><?php echo $dh($r['guest_name']); ?></div>
                    <small style="color:var(--primary); font-weight:600;"><?php echo $dh(strtoupper((string)$r['category'])); ?></small>
                </td>
                <td><span class="discount-status <?php echo $st; ?>"><?php echo $dh($r['state']); ?></span></td>
                <td style="color:var(--text-muted); font-size:13px;"><?php echo $dh($r['hold_until']); ?></td>
                <td style="text-align:right;">
                    <a href="?booking=<?php echo (int)$r['booking_id']; ?>" class="action-link">Review &rarr;</a>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
