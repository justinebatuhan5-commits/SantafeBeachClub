Add-Type -AssemblyName System.Drawing
$ErrorActionPreference = 'Stop'

$outDir = Join-Path $PSScriptRoot '..\flowcharts'
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$navy=[Drawing.Color]::FromArgb(23,55,78); $blue=[Drawing.Color]::FromArgb(66,126,190)
$orange=[Drawing.Color]::FromArgb(235,132,72); $green=[Drawing.Color]::FromArgb(55,148,102)
$red=[Drawing.Color]::FromArgb(211,84,78); $ink=[Drawing.Color]::FromArgb(38,47,54)
$muted=[Drawing.Color]::FromArgb(105,118,128); $paper=[Drawing.Color]::White
$softBlue=[Drawing.Color]::FromArgb(239,246,255); $softOrange=[Drawing.Color]::FromArgb(255,245,235)
$softGreen=[Drawing.Color]::FromArgb(237,249,242); $softRed=[Drawing.Color]::FromArgb(255,240,239)

$titleFont=[Drawing.Font]::new('Segoe UI',24,[Drawing.FontStyle]::Bold)
$subFont=[Drawing.Font]::new('Segoe UI',10,[Drawing.FontStyle]::Regular)
$nodeFont=[Drawing.Font]::new('Segoe UI',11,[Drawing.FontStyle]::Regular)
$labelFont=[Drawing.Font]::new('Segoe UI',9,[Drawing.FontStyle]::Bold)
$tinyFont=[Drawing.Font]::new('Segoe UI',8,[Drawing.FontStyle]::Regular)

function New-Canvas($title,$subtitle) {
    $bmp=[Drawing.Bitmap]::new(1200,1600); $g=[Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode=[Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $g.TextRenderingHint=[Drawing.Text.TextRenderingHint]::ClearTypeGridFit
    $g.Clear($paper)
    $g.DrawString($title,$titleFont,[Drawing.SolidBrush]::new($navy),55,38)
    $g.DrawString($subtitle,$subFont,[Drawing.SolidBrush]::new($muted),58,82)
    $g.DrawLine([Drawing.Pen]::new([Drawing.Color]::FromArgb(220,228,234),2),55,115,1145,115)
    return @{Bitmap=$bmp; Graphics=$g}
}

function Box($g,$x,$y,$w,$h,$text,$fill=$softBlue,$border=$blue) {
    $r=[Drawing.RectangleF]::new($x,$y,$w,$h)
    $g.FillRectangle([Drawing.SolidBrush]::new($fill),$r); $g.DrawRectangle([Drawing.Pen]::new($border,2),$x,$y,$w,$h)
    $f=[Drawing.StringFormat]::new(); $f.Alignment='Center'; $f.LineAlignment='Center'
    $g.DrawString($text,$nodeFont,[Drawing.SolidBrush]::new($ink),$r,$f)
}
function Terminator($g,$x,$y,$w,$h,$text) {
    $r=[Drawing.RectangleF]::new($x,$y,$w,$h)
    $g.FillEllipse([Drawing.SolidBrush]::new($softBlue),$r); $g.DrawEllipse([Drawing.Pen]::new($blue,2),$r)
    $f=[Drawing.StringFormat]::new(); $f.Alignment='Center'; $f.LineAlignment='Center'
    $g.DrawString($text,$nodeFont,[Drawing.SolidBrush]::new($ink),$r,$f)
}
function Decision($g,$cx,$cy,$w,$h,$text) {
    $p=[Drawing.PointF[]]@([Drawing.PointF]::new($cx,$cy-$h/2),[Drawing.PointF]::new($cx+$w/2,$cy),[Drawing.PointF]::new($cx,$cy+$h/2),[Drawing.PointF]::new($cx-$w/2,$cy))
    $g.FillPolygon([Drawing.SolidBrush]::new($softOrange),$p); $g.DrawPolygon([Drawing.Pen]::new($orange,2),$p)
    $f=[Drawing.StringFormat]::new(); $f.Alignment='Center'; $f.LineAlignment='Center'
    $r=[Drawing.RectangleF]::new($cx-$w*.31,$cy-$h*.25,$w*.62,$h*.5)
    $g.DrawString($text,$nodeFont,[Drawing.SolidBrush]::new($ink),$r,$f)
}
function Arrow($g,$x1,$y1,$x2,$y2,$label='') {
    $p=[Drawing.Pen]::new($navy,2); $p.CustomEndCap=[Drawing.Drawing2D.AdjustableArrowCap]::new(5,7)
    $g.DrawLine($p,$x1,$y1,$x2,$y2)
    if($label){$g.DrawString($label,$labelFont,[Drawing.SolidBrush]::new($navy),($x1+$x2)/2+5,($y1+$y2)/2-18)}
}
function PolyArrow($g,[float[]]$pts,$label='') {
    $p=[Drawing.Pen]::new($navy,2); $p.CustomEndCap=[Drawing.Drawing2D.AdjustableArrowCap]::new(5,7)
    $arr=New-Object Drawing.PointF[] ($pts.Length/2)
    for($i=0;$i -lt $pts.Length;$i+=2){$arr[$i/2]=[Drawing.PointF]::new($pts[$i],$pts[$i+1])}
    $g.DrawLines($p,$arr)
    if($label){$g.DrawString($label,$labelFont,[Drawing.SolidBrush]::new($navy),$pts[2]+6,$pts[3]-20)}
}
function Note($g,$x,$y,$w,$h,$text,$fill=$softGreen,$border=$green) { Box $g $x $y $w $h $text $fill $border }
function Save-Canvas($canvas,$name) {
    $path=Join-Path $outDir $name; $canvas.Bitmap.Save($path,[Drawing.Imaging.ImageFormat]::Png)
    $canvas.Graphics.Dispose(); $canvas.Bitmap.Dispose(); Write-Output (Resolve-Path $path)
}

# 1. Reservation and payment
$c=New-Canvas '1. Guest reservation and payment' 'Public booking flow, including standard and Senior/PWD discount requests'
$g=$c.Graphics
Terminator $g 470 140 260 55 'Start'
Box $g 400 235 400 75 'Browse rooms and select dates / guest count'
Decision $g 600 390 330 140 'Room available?'
Box $g 400 520 400 75 'Enter guest details and arrival time'
Box $g 400 645 400 75 'Review price, deposit and promo options'
Decision $g 600 840 340 145 'Senior/PWD discount request?'
Box $g 115 965 340 90 'Upload valid ID and submit for staff review' $softOrange $orange
Box $g 745 965 340 90 'Choose PayMongo or manual GCash / bank' $softBlue $blue
Decision $g 285 1165 300 135 'Discount approved?'
Box $g 745 1125 340 80 'Create booking as Pending Payment'
Box $g 430 1295 340 80 'Verify payment and confirm booking' $softGreen $green
Terminator $g 470 1445 260 55 'Confirmation email + QR'
Arrow $g 600 195 600 235; Arrow $g 600 310 600 320; Arrow $g 600 460 600 520 'Yes'
PolyArrow $g ([float[]](435,390,85,390,85,272,400,272)) 'No: revise'
Arrow $g 600 595 600 645; Arrow $g 600 720 600 768
Arrow $g 490 905 285 965 'Yes'; Arrow $g 710 905 915 965 'No'
Arrow $g 285 1055 285 1098; PolyArrow $g ([float[]](435,1165,600,1165,600,1250,600,1295)) 'Yes'
PolyArrow $g ([float[]](285,1233,285,1260,150,1260,150,1010)) 'No / resubmit'
Arrow $g 915 1055 915 1125; PolyArrow $g ([float[]](915,1205,915,1250,770,1250,770,1335))
Arrow $g 600 1375 600 1445
Save-Canvas $c '01-guest-reservation-and-payment.png'

# 2. Confirmed booking and stay
$c=New-Canvas '2. Confirmed booking, check-in and check-out' 'Operational flow after the deposit or full payment has been verified'
$g=$c.Graphics
Terminator $g 470 145 260 55 'Confirmed booking'
Box $g 400 250 400 80 'Send confirmation email and check-in QR'
Box $g 400 385 400 80 'Guest arrives; staff searches booking or scans QR'
Decision $g 600 565 330 140 'Guest arrives before cutoff?'
Box $g 400 690 400 80 'Confirm identity, booking dates and room assignment'
Decision $g 600 875 330 140 'Remaining balance due?'
Box $g 135 1000 340 80 'Collect and record balance payment' $softOrange $orange
Box $g 725 1000 340 80 'Continue with paid booking' $softGreen $green
Box $g 400 1155 400 80 'Check in guest and mark room Occupied' $softGreen $green
Box $g 400 1285 400 75 'Guest stay'
Box $g 400 1410 400 75 'Check out; mark room Maintenance and record revenue' $softGreen $green
Arrow $g 600 200 600 250; Arrow $g 600 330 600 385; Arrow $g 600 465 600 495
Arrow $g 600 635 600 690 'Yes'; Arrow $g 600 770 600 805
PolyArrow $g ([float[]](435,565,115,565,115,1515,470,1515)) 'No: no-show handling'
Arrow $g 490 935 305 1000 'Yes'; Arrow $g 710 935 895 1000 'No'
PolyArrow $g ([float[]](305,1080,305,1115,520,1115,520,1155)); PolyArrow $g ([float[]](895,1080,895,1115,680,1115,680,1155))
Arrow $g 600 1235 600 1285; Arrow $g 600 1360 600 1410
Save-Canvas $c '02-confirmed-booking-checkin-checkout.png'

# 3. Guest portal, cancellation and refund
$c=New-Canvas '3. Guest portal, cancellation and refund' 'Private booking access through email, booking reference and OTP verification'
$g=$c.Graphics
Terminator $g 470 140 260 55 'Open My Booking'
Box $g 400 235 400 75 'Enter email and booking reference'
Decision $g 600 390 320 140 'Matching booking?'
Box $g 400 515 400 75 'Send and verify email OTP'
Decision $g 600 680 320 140 'OTP valid?'
Box $g 400 805 400 80 'View booking, payment status and QR'
Decision $g 600 990 340 145 'Guest action?'
Box $g 60 1125 300 80 'View details / download QR' $softBlue $blue
Box $g 450 1125 300 80 'Cancel eligible booking' $softRed $red
Box $g 840 1125 300 80 'Rebook with saved guest details' $softBlue $blue
Decision $g 600 1330 320 140 'Request refund?'
Box $g 115 1440 360 75 'End: cancellation recorded' $softRed $red
Box $g 725 1440 360 75 'Submit request for manager review' $softOrange $orange
Arrow $g 600 195 600 235; Arrow $g 600 310 600 320; Arrow $g 600 460 600 515 'Yes'
PolyArrow $g ([float[]](440,390,125,390,125,272,400,272)) 'No: retry'
Arrow $g 600 590 600 610; Arrow $g 600 750 600 805 'Yes'
PolyArrow $g ([float[]](440,680,165,680,165,552,400,552)) 'No: resend'
Arrow $g 600 885 600 918
Arrow $g 470 1045 210 1125 'View'; Arrow $g 600 1062 600 1125 'Cancel'; Arrow $g 730 1045 990 1125 'Rebook'
Arrow $g 600 1205 600 1260; Arrow $g 490 1390 295 1440 'No'; Arrow $g 710 1390 905 1440 'Yes'
Save-Canvas $c '03-guest-portal-cancellation-refund.png'

# 4. Staff and admin operations
$c=New-Canvas '4. Staff and administrator operations' 'Role-based dashboard for reception, booking management and administration'
$g=$c.Graphics
Terminator $g 470 140 260 55 'Staff / administrator'
Box $g 400 235 400 75 'Log in'
Decision $g 600 390 320 140 'Authentication accepted?'
Box $g 400 515 400 75 'Open dashboard with role permissions'
Decision $g 600 690 340 145 'Choose operation'
Box $g 75 835 290 75 'Reservations and calendar'
Box $g 455 835 290 75 'Payments and discount requests'
Box $g 835 835 290 75 'Arrivals / departures'
Box $g 75 1015 290 75 'Rooms and maintenance'
Box $g 455 1015 290 75 'Refund review and processing' $softOrange $orange
Box $g 835 1015 290 75 'Staff, room types and promotions'
Box $g 265 1195 290 75 'Reports and analytics'
Box $g 645 1195 290 75 'Notifications, logs and backups'
Box $g 400 1375 400 75 'Save changes and return to dashboard' $softGreen $green
Terminator $g 470 1500 260 55 'Log out'
Arrow $g 600 195 600 235; Arrow $g 600 310 600 320; Arrow $g 600 460 600 515 'Yes'
PolyArrow $g ([float[]](440,390,170,390,170,272,400,272)) 'No'
Arrow $g 600 590 600 618
Arrow $g 500 750 220 835; Arrow $g 550 760 600 835; Arrow $g 700 750 980 835
PolyArrow $g ([float[]](470,720,220,720,220,1015)); Arrow $g 600 762 600 1015; PolyArrow $g ([float[]](730,720,980,720,980,1015))
PolyArrow $g ([float[]](530,750,410,1195)); PolyArrow $g ([float[]](670,750,790,1195))
PolyArrow $g ([float[]](220,1090,220,1330,500,1330,500,1375)); PolyArrow $g ([float[]](600,1090,600,1375)); PolyArrow $g ([float[]](980,1090,980,1330,700,1330,700,1375))
PolyArrow $g ([float[]](410,1270,410,1340,540,1340,540,1375)); PolyArrow $g ([float[]](790,1270,790,1340,660,1340,660,1375))
Arrow $g 600 1450 600 1500
Save-Canvas $c '04-staff-admin-operations.png'
