Add-Type -AssemblyName System.Drawing
$ErrorActionPreference = 'Stop'

$outputPath = Join-Path $PSScriptRoot '..\Santa-Beach-Club-System-Flowchart.png'
$width = 2400
$height = 1900
$bmp = New-Object System.Drawing.Bitmap($width, $height)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::ClearTypeGridFit

$navy = [System.Drawing.Color]::FromArgb(18, 52, 77)
$blue = [System.Drawing.Color]::FromArgb(35, 112, 166)
$teal = [System.Drawing.Color]::FromArgb(35, 151, 145)
$sand = [System.Drawing.Color]::FromArgb(242, 184, 92)
$coral = [System.Drawing.Color]::FromArgb(232, 108, 92)
$green = [System.Drawing.Color]::FromArgb(66, 153, 99)
$lightBlue = [System.Drawing.Color]::FromArgb(234, 245, 252)
$lightTeal = [System.Drawing.Color]::FromArgb(232, 248, 246)
$lightSand = [System.Drawing.Color]::FromArgb(255, 247, 229)
$white = [System.Drawing.Color]::White
$dark = [System.Drawing.Color]::FromArgb(33, 47, 56)
$muted = [System.Drawing.Color]::FromArgb(89, 108, 119)

$g.Clear([System.Drawing.Color]::FromArgb(247, 250, 252))
$titleFont = New-Object System.Drawing.Font('Segoe UI', 34, [System.Drawing.FontStyle]::Bold)
$subFont = New-Object System.Drawing.Font('Segoe UI', 15, [System.Drawing.FontStyle]::Regular)
$laneFont = New-Object System.Drawing.Font('Segoe UI', 19, [System.Drawing.FontStyle]::Bold)
$nodeFont = New-Object System.Drawing.Font('Segoe UI', 14, [System.Drawing.FontStyle]::Bold)
$smallFont = New-Object System.Drawing.Font('Segoe UI', 11, [System.Drawing.FontStyle]::Regular)
$labelFont = New-Object System.Drawing.Font('Segoe UI', 10, [System.Drawing.FontStyle]::Bold)

$g.DrawString('Santa Beach Club Booking System', $titleFont, (New-Object System.Drawing.SolidBrush($navy)), 80, 45)
$g.DrawString('End-to-end reservation, payment, stay, cancellation and refund workflow', $subFont, (New-Object System.Drawing.SolidBrush($muted)), 84, 105)

function RoundRect([float]$x,[float]$y,[float]$w,[float]$h,[float]$r) {
    $p = New-Object System.Drawing.Drawing2D.GraphicsPath
    $d = $r * 2
    $p.AddArc($x,$y,$d,$d,180,90); $p.AddArc($x+$w-$d,$y,$d,$d,270,90)
    $p.AddArc($x+$w-$d,$y+$h-$d,$d,$d,0,90); $p.AddArc($x,$y+$h-$d,$d,$d,90,90)
    $p.CloseFigure(); return $p
}

function Draw-Node($x,$y,$w,$h,$text,$fill,$border=$blue) {
    $path = RoundRect $x $y $w $h 18
    $g.FillPath((New-Object System.Drawing.SolidBrush($fill)), $path)
    $g.DrawPath((New-Object System.Drawing.Pen($border, 3)), $path)
    $format = New-Object System.Drawing.StringFormat
    $format.Alignment = [System.Drawing.StringAlignment]::Center
    $format.LineAlignment = [System.Drawing.StringAlignment]::Center
    $box = [System.Drawing.RectangleF]::new([single]($x+12),[single]($y+6),[single]($w-24),[single]($h-12))
    $g.DrawString([string]$text, $nodeFont, (New-Object System.Drawing.SolidBrush($dark)), $box, $format)
}

function Draw-Decision($cx,$cy,$w,$h,$text) {
    $pts = [System.Drawing.PointF[]]@(
        [System.Drawing.PointF]::new([single]$cx,[single]($cy-$h/2)),[System.Drawing.PointF]::new([single]($cx+$w/2),[single]$cy),
        [System.Drawing.PointF]::new([single]$cx,[single]($cy+$h/2)),[System.Drawing.PointF]::new([single]($cx-$w/2),[single]$cy))
    $g.FillPolygon((New-Object System.Drawing.SolidBrush($lightSand)), $pts)
    $g.DrawPolygon((New-Object System.Drawing.Pen($sand,3)), $pts)
    $format = New-Object System.Drawing.StringFormat
    $format.Alignment = [System.Drawing.StringAlignment]::Center; $format.LineAlignment = [System.Drawing.StringAlignment]::Center
    $box = [System.Drawing.RectangleF]::new([single]($cx-$w*.34),[single]($cy-$h*.28),[single]($w*.68),[single]($h*.56))
    $g.DrawString([string]$text,$nodeFont,(New-Object System.Drawing.SolidBrush($dark)),$box,$format)
}

function Draw-Arrow($x1,$y1,$x2,$y2,$label='') {
    $pen = New-Object System.Drawing.Pen($navy,3)
    $pen.CustomEndCap = New-Object System.Drawing.Drawing2D.AdjustableArrowCap(6,8)
    $g.DrawLine($pen,$x1,$y1,$x2,$y2)
    if ($label) { $g.DrawString($label,$labelFont,(New-Object System.Drawing.SolidBrush($navy)),(($x1+$x2)/2+6),(($y1+$y2)/2-20)) }
}

# Swimlanes
$lanes = @(
    @{Y=170; H=500; Name='GUEST'; Fill=$lightBlue; Band=$blue},
    @{Y=690; H=570; Name='BOOKING SYSTEM'; Fill=$lightTeal; Band=$teal},
    @{Y=1280; H=500; Name='STAFF / ADMIN'; Fill=$lightSand; Band=$sand}
)
foreach ($lane in $lanes) {
    $g.FillRectangle((New-Object System.Drawing.SolidBrush($lane.Fill)), 70, $lane.Y, 2260, $lane.H)
    $g.FillRectangle((New-Object System.Drawing.SolidBrush($lane.Band)), 70, $lane.Y, 200, $lane.H)
    $format = New-Object System.Drawing.StringFormat
    $format.Alignment=[System.Drawing.StringAlignment]::Center; $format.LineAlignment=[System.Drawing.StringAlignment]::Center
    $g.DrawString($lane.Name,$laneFont,(New-Object System.Drawing.SolidBrush($white)),(New-Object System.Drawing.RectangleF(80,$lane.Y,180,$lane.H)),$format)
}

# Guest journey
Draw-Node 330 240 270 100 'Browse rooms and choose dates / guests' $white
Draw-Decision 760 290 260 160 'Room available?'
Draw-Node 960 240 280 100 'Enter guest details and arrival time' $white
Draw-Node 1320 240 280 100 'Review price, deposit and discounts' $white
Draw-Decision 1770 290 260 160 'Special discount?'
Draw-Node 1990 240 270 100 'Choose payment method and submit' $white
Draw-Arrow 600 290 630 290
Draw-Arrow 890 290 960 290 'Yes'
Draw-Arrow 1240 290 1320 290
Draw-Arrow 1600 290 1640 290
Draw-Arrow 1900 290 1990 290
Draw-Arrow 760 370 470 370 'No: revise search'

Draw-Node 520 500 300 100 'Receive confirmation email and check-in QR' $white $green
Draw-Node 970 500 300 100 'Arrive, present booking / QR and pay balance' $white $green
Draw-Node 1420 500 300 100 'Stay at Santa Beach Club' $white $green
Draw-Node 1870 500 300 100 'View booking, cancel or request refund' $white $coral
Draw-Arrow 820 550 970 550
Draw-Arrow 1270 550 1420 550

# System processing
Draw-Node 330 760 290 105 'Validate dates, capacity, guest data and pricing' $white
Draw-Decision 790 812 270 170 'Payment route?'
Draw-Node 1010 735 300 105 'PayMongo secure checkout' $white
Draw-Node 1010 875 300 105 'Manual GCash / bank receipt' $white
Draw-Node 1420 805 300 105 'Save booking as Pending Payment' $white
Draw-Decision 1890 857 270 175 'Payment verified?'
Draw-Arrow 620 812 655 812
Draw-Arrow 925 790 1010 787 'Online'
Draw-Arrow 925 855 1010 927 'Manual'
Draw-Arrow 1310 787 1420 840
Draw-Arrow 1310 927 1420 875
Draw-Arrow 1720 857 1755 857

Draw-Node 340 1080 300 105 'Confirm booking, reserve inventory and send QR' $white $green
Draw-Node 820 1080 300 105 'Check in: mark room Occupied' $white $green
Draw-Node 1300 1080 300 105 'Check out: mark room Maintenance and earn revenue' $white $green
Draw-Node 1810 1080 360 105 'Cancellation / refund status and guest notifications' $white $coral
Draw-Arrow 1755 900 640 1132 'Yes'
Draw-Arrow 640 1132 820 1132
Draw-Arrow 1120 1132 1300 1132

# Staff/admin
Draw-Node 330 1360 300 105 'Log in and open role-based dashboard' $white
Draw-Decision 800 1412 280 175 'Choose operation'
Draw-Node 1030 1325 280 100 'Review reservations and calendar' $white
Draw-Node 1030 1450 280 100 'Verify payments and discount IDs' $white
Draw-Node 1420 1325 280 100 'Check guests in / out and manage rooms' $white
Draw-Node 1420 1450 280 100 'Review refund requests: approve or reject' $white
Draw-Node 1810 1325 340 100 'Manage staff, room types, promotions and gallery' $white
Draw-Node 1810 1450 340 100 'Reports, analytics, notifications, logs and backups' $white
Draw-Arrow 630 1412 660 1412
Draw-Arrow 940 1375 1030 1375
Draw-Arrow 940 1448 1030 1500
Draw-Arrow 1310 1375 1420 1375
Draw-Arrow 1310 1500 1420 1500
Draw-Arrow 1700 1375 1810 1375
Draw-Arrow 1700 1500 1810 1500

# Cross-lane handoffs
Draw-Arrow 2125 340 2125 760 'Submit'
Draw-Arrow 1890 945 1890 1015 'No: pending / retry'
Draw-Arrow 1120 980 1120 1450 'Staff review'
Draw-Arrow 490 1080 490 600 'Confirmation'
Draw-Arrow 970 600 970 1080 'Arrival'
Draw-Arrow 1450 600 1450 1080 'Departure'
Draw-Arrow 2020 600 2020 1080 'Cancel / refund'
Draw-Arrow 1590 1450 1980 1185 'Decision update'

# Discount-review branch annotation
$g.FillRectangle((New-Object System.Drawing.SolidBrush([System.Drawing.Color]::FromArgb(255,255,255))), 1660, 405, 570, 58)
$g.DrawRectangle((New-Object System.Drawing.Pen($sand,2)),1660,405,570,58)
$g.DrawString('Senior/PWD: hold room -> staff reviews ID -> approved price',$smallFont,(New-Object System.Drawing.SolidBrush($dark)),1680,423)
Draw-Arrow 1770 370 1770 405 'Yes'

# Legend / footer
$g.FillRectangle((New-Object System.Drawing.SolidBrush($navy)),70,1810,2260,55)
$g.DrawString('Status path: Pending Payment -> Confirmed -> Checked In -> Checked Out     |     Alternate path: Cancelled -> Refund Requested -> Approved/Rejected -> Completed',$smallFont,(New-Object System.Drawing.SolidBrush($white)),105,1828)

$bmp.Save($outputPath, [System.Drawing.Imaging.ImageFormat]::Png)
$g.Dispose(); $bmp.Dispose()
Write-Output (Resolve-Path $outputPath)
