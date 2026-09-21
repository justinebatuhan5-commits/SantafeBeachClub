<?php
// Local-only database tests. All tables are temporary and shadow real tables.
$render = in_array('--render-staff',$argv,true) ? 'staff' : (in_array('--render-guest',$argv,true) ? 'guest' : null);
if ($render) {
    ob_start();
    ini_set('session.save_path',sys_get_temp_dir());
    // Rendering boots only the explicitly selected local development database.
    foreach (['DB_HOST'=>'127.0.0.1','DB_PORT'=>'3307','DB_NAME'=>'santafe_beach_club','DB_USER'=>'root','DB_PASS'=>''] as $key=>$value) putenv($key.'='.$value);
    $_SERVER['REQUEST_METHOD']='GET'; $_SERVER['HTTP_HOST']='localhost';
    require_once __DIR__ . '/../backend/config/db.php';
}
require_once __DIR__ . '/../backend/services/discount_service.php';
function expect_discount(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function rejects_discount(callable $call, string $message): void { try { $call(); } catch (Throwable $e) { return; } throw new RuntimeException($message); }
$a=discount_amounts(1000000);
expect_discount($a===['discount'=>200000,'total'=>800000,'deposit'=>400000], '20% entire room and 50% deposit calculation failed');
expect_discount(discount_amounts(100001)['deposit'] + (discount_amounts(100001)['total']-discount_amounts(100001)['deposit'])===discount_amounts(100001)['total'],'Centavo rounding failed');
expect_discount(discount_amounts(1000000,false)['total']===1000000,'Regular price acceptance calculation failed');
rejects_discount(fn()=>discount_amounts(0),'Zero amount accepted');
rejects_discount(fn()=>discount_upload([], 'discount_ids'),'Missing ID accepted');
echo "Discount calculation and missing-upload checks passed.\n";
$savedBase=getenv('PUBLIC_APP_URL');
foreach (['https://example.com','https://example.com/frontend','https://example.com/project/frontend/'] as $base) {
    putenv('PUBLIC_APP_URL='.$base);
    $link=discount_link(['token'=>str_repeat('a',64)]);
    expect_discount(str_contains($link,'/frontend/discount_booking.php#access='),'Private link does not use the real frontend route');
    expect_discount(!str_contains($link,'?token=') && !str_contains($link,'/frontend/frontend/'),'Private link exposes a query token or duplicates frontend');
}
putenv($savedBase===false?'PUBLIC_APP_URL':'PUBLIC_APP_URL='.$savedBase);
if (!in_array('--database',$argv,true)) exit;
date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
class DiscountTestDb extends mysqli {
    public function query(string $query, int $result_mode=MYSQLI_STORE_RESULT): mysqli_result|bool {
        return parent::query(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE IF NOT EXISTS',$query),$result_mode);
    }
}
$db=new DiscountTestDb('127.0.0.1','root','','santafe_beach_club',3307);
$db->query("SET time_zone='+08:00'");
$db->query("CREATE TEMPORARY TABLE bookings (id INT PRIMARY KEY, status VARCHAR(30),room_id INT,check_in DATE,check_out DATE,guests_count INT,discount_amount DECIMAL(10,2),promo_code VARCHAR(50), guest_name VARCHAR(100),guest_email VARCHAR(150),guest_phone VARCHAR(30)) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE payments (id INT AUTO_INCREMENT PRIMARY KEY,booking_id INT,amount DECIMAL(10,2),payment_method VARCHAR(50),transaction_id VARCHAR(100),status VARCHAR(30),guest_name VARCHAR(100),guest_email VARCHAR(150),receipt_url TEXT) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE payment_action_history (payment_id INT,action VARCHAR(30),performed_by VARCHAR(100),details TEXT) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE notifications (title VARCHAR(100),message TEXT,type VARCHAR(30),booking_id INT) ENGINE=InnoDB");
discount_schema($db); pm_checkout_schema($db);
function seed_discount(mysqli $db,int $id): void {
    pm_query($db,"INSERT INTO bookings VALUES (?,'Pending Payment',1,'2027-01-02','2027-01-04',2,0,'PROMO','Test','test@example.com','')",[$id]);
    pm_query($db,"INSERT INTO booking_discounts (booking_id,category,eligible_guest,id_file,token,original_centavos,total_centavos,deposit_centavos,room_id,check_in,check_out,guests_count,hold_until) VALUES (?,'pwd','Test','fixture.png',?,1000000,800000,400000,1,'2027-01-02','2027-01-04',2,DATE_ADD(NOW(),INTERVAL 1 DAY))",[$id,str_pad((string)$id,64,'0',STR_PAD_LEFT)]);
}
seed_discount($db,1);
discount_review($db,1,'approve','Verified ID','Reception');
expect_discount($db->query('SELECT promo_code FROM bookings WHERE id=1')->fetch_row()[0]===null,'Promo stacked with discount');
expect_discount((float)$db->query('SELECT discount_amount FROM bookings WHERE id=1')->fetch_row()[0]===2000.0,'Approved discount not saved');
rejects_discount(fn()=>discount_review($db,1,'approve','','Admin'),'Double approval accepted');
$db->query("INSERT INTO payments (id,booking_id,amount,payment_method,transaction_id,status) VALUES (1,1,4000,'GCash QR','ref1','pending')");
$db->query("INSERT INTO discount_payments (payment_id,booking_id,purpose) VALUES (1,1,'deposit')");
$db->query("UPDATE booking_discounts SET state='Payment Under Review' WHERE booking_id=1");
discount_manual_review($db,1,1,true,'Reception','Actual transfer checked');
expect_discount($db->query('SELECT status FROM bookings WHERE id=1')->fetch_row()[0]==='Confirmed','Deposit did not confirm');
rejects_discount(fn()=>discount_manual_review($db,1,1,true,'Reception',''),'Duplicate manual verification accepted');
$db->query("INSERT INTO payments (id,booking_id,amount,payment_method,transaction_id,status) VALUES (2,1,4000,'Bank Deposit','ref2','pending')");
$db->query("INSERT INTO discount_payments (payment_id,booking_id,purpose) VALUES (2,1,'balance')");
discount_manual_review($db,1,2,true,'Admin','Balance received');
expect_discount(discount_due($db,$db->query('SELECT * FROM booking_discounts WHERE booking_id=1')->fetch_assoc())===0,'Balance remains after full payment');
seed_discount($db,2);
discount_review($db,2,'information','Upload clearer ID','Admin');
expect_discount($db->query('SELECT state FROM booking_discounts WHERE booking_id=2')->fetch_row()[0]==='More information needed','Information request failed');
discount_review($db,2,'decline','ID does not match staying guest','Admin');
expect_discount((int)$db->query('SELECT total_centavos FROM booking_discounts WHERE booking_id=2')->fetch_row()[0]===1000000,'Declined request still displays discounted price');
rejects_discount(fn()=>discount_start_payment($db,2,'PayMongo Online'),'Declined booking allowed checkout');
seed_discount($db,3);
$db->query("UPDATE booking_discounts SET hold_until=DATE_SUB(NOW(),INTERVAL 1 HOUR) WHERE booking_id=3");
discount_maintenance($db);
expect_discount($db->query('SELECT status FROM bookings WHERE id=3')->fetch_row()[0]==='Cancelled','Expired hold not released');
rejects_discount(fn()=>discount_review($db,3,'approve','','Admin'),'Expired request approved');
seed_discount($db,4); discount_review($db,4,'approve','','Admin');
$db->query("UPDATE bookings SET guests_count=3 WHERE id=4");
rejects_discount(fn()=>discount_start_payment($db,4,'PayMongo Online'),'Changed booking used stale price');
seed_discount($db,5); discount_review($db,5,'approve','','Admin');
$db->query("INSERT INTO payments (id,booking_id,amount,payment_method,transaction_id,status) VALUES (5,5,4000,'PayMongo Online','initial','pending')");
$db->query("INSERT INTO discount_payments (payment_id,booking_id,purpose) VALUES (5,5,'deposit')");
pm_store_checkout($db,'cs_discount',5,5,4000);
$session=['id'=>'cs_discount','attributes'=>['metadata'=>['booking_id'=>'5'],'payments'=>[['id'=>'pay_discount','attributes'=>['status'=>'paid','amount'=>400000,'currency'=>'PHP','source'=>['type'=>'gcash']]]]]];
$bad=$session; $bad['attributes']['payments'][0]['attributes']['amount']=1;
rejects_discount(fn()=>pm_confirm_checkout($db,5,'cs_discount',$bad),'Wrong payment amount accepted');
expect_discount(pm_confirm_checkout($db,5,'cs_discount',$session)['new'],'Signed payment not applied');
expect_discount(!pm_confirm_checkout($db,5,'cs_discount',$session)['new'],'Duplicate webhook reapplied');
expect_discount($db->query('SELECT state FROM booking_discounts WHERE booking_id=5')->fetch_row()[0]==='Confirmed','Webhook did not confirm discount state');
seed_discount($db,6); discount_review($db,6,'approve','','Admin');
$db->query("INSERT INTO payments (id,booking_id,amount,payment_method,transaction_id,status) VALUES (6,6,3999,'GCash QR','ref6','pending')");
$db->query("INSERT INTO discount_payments (payment_id,booking_id,purpose) VALUES (6,6,'deposit')");
$db->query("UPDATE booking_discounts SET state='Payment Under Review' WHERE booking_id=6");
rejects_discount(fn()=>discount_manual_review($db,6,6,true,'Reception',''),'Underpayment accepted');
expect_discount($db->query('SELECT status FROM payments WHERE id=6')->fetch_row()[0]==='pending','Failed verification did not roll back');
echo "Temporary-table integration passed: approval, no stacking, manual deposit/balance, duplicate rejection, decline, information request, expiry, changed booking, PayMongo amount binding/idempotency and rollback.\n";
if ($render) {
    seed_discount($db,7);
    $db->query("ALTER TABLE bookings ADD accommodation_name VARCHAR(100) DEFAULT 'Standard Family Room'");
    $db->query("CREATE TEMPORARY TABLE settings (setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT)");
    $db->query("INSERT INTO settings VALUES ('gcash_number','TEST ACCOUNT'),('gcash_name','Hotel Test Account')");
    $conn=$db;
    $_SESSION['admin_logged_in']=true; $_SESSION['admin_role']='receptionist'; $_SESSION['admin_username']='Test Reception';
    $_SESSION['session_start_time']=time(); $_SESSION['last_activity']=time();
    $_SESSION['discount_tokens'][5]=str_pad('5',64,'0',STR_PAD_LEFT);
    $_GET=['booking'=>$render==='staff'?7:5];
    $_SERVER['SCRIPT_NAME']='/SantaBeachClub-BookingSystem/frontend/discount_'.($render==='staff'?'requests':'booking').'.php';
    ob_clean();
    require __DIR__.'/../frontend/discount_'.($render==='staff'?'requests':'booking').'.php';
    $html=str_replace('<head>','<head><base href="../frontend/">',ob_get_clean());
    file_put_contents(__DIR__.'/discount-preview-'.$render.'.html',$html);
    session_destroy();
    echo "Rendered synthetic $render preview.\n";
}
$db->close();
