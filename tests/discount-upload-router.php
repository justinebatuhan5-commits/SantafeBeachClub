<?php
// Test-only HTTP harness. Refuses Apache/production requests and requires a token.
if (PHP_SAPI !== 'cli-server' || !getenv('DISCOUNT_TEST_TOKEN') || !hash_equals(getenv('DISCOUNT_TEST_TOKEN'), $_SERVER['HTTP_X_DISCOUNT_TEST'] ?? '')) { http_response_code(403); exit; }
require_once __DIR__ . '/../backend/services/discount_service.php';
date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
class DiscountUploadTestDb extends mysqli {
    public function query(string $query,int $result_mode=MYSQLI_STORE_RESULT): mysqli_result|bool {
        return parent::query(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE IF NOT EXISTS',$query),$result_mode);
    }
}
$db=new DiscountUploadTestDb('127.0.0.1','root','','santafe_beach_club',3307);
$db->query("SET time_zone='+08:00'");
$db->query("CREATE TEMPORARY TABLE rooms (id INT PRIMARY KEY,name VARCHAR(100),type VARCHAR(50),status VARCHAR(30)) ENGINE=InnoDB");
$db->query("INSERT INTO rooms VALUES (1,'Test Room','standard_room','ready')");
$db->query("CREATE TEMPORARY TABLE bookings (id INT AUTO_INCREMENT PRIMARY KEY,guest_name VARCHAR(100),guest_email VARCHAR(150),guest_type VARCHAR(50),check_in DATE,check_out DATE,guests_count INT,room_id INT,accommodation_name VARCHAR(100),eta VARCHAR(10),status VARCHAR(30),checkin_token VARCHAR(50),cancellation_token VARCHAR(50),payment_method VARCHAR(50),guest_phone VARCHAR(30),discount_amount DECIMAL(10,2)) ENGINE=InnoDB");
$db->query("CREATE TEMPORARY TABLE notifications (title VARCHAR(100),message TEXT,type VARCHAR(30),booking_id INT) ENGINE=InnoDB");
discount_schema($db);
$fixture=sys_get_temp_dir().'/sbc-discount-upload-'.bin2hex(random_bytes(8));
mkdir($fixture); mkdir($fixture.'/www');
putenv('PRIVATE_STORAGE_DOCUMENT_ROOT='.$fixture.'/www'); putenv('PRIVATE_STORAGE_DIR='.$fixture.'/private');
try {
    $result=discount_create($db,['category'=>'pwd','eligible_guest'=>'Synthetic Test Guest','name'=>'Synthetic Test Guest','email'=>'test@example.com','checkin'=>date('Y-m-d',strtotime('+10 days')),'checkout'=>date('Y-m-d',strtotime('+12 days')),'guests'=>2,'original'=>1000000,'room_type'=>'standard_room','eta'=>'14:00','phone'=>''],$_FILES['id']??[]);
    $saved=$db->query('SELECT state,total_centavos,deposit_centavos,id_file FROM booking_discounts')->fetch_assoc();
    $path=private_storage_file('discount_ids/'.$saved['id_file']);
    if (!$path || $saved['state']!=='Awaiting ID verification' || (int)$saved['total_centavos']!==800000 || (int)$saved['deposit_centavos']!==400000) throw new RuntimeException('Creation assertion failed.');
    if ($db->query('SELECT COUNT(*) FROM bookings')->fetch_row()[0]!=1) throw new RuntimeException('Unexpected booking count.');
    header('Content-Type: application/json'); echo json_encode(['success'=>true,'private_id'=>true,'state'=>$saved['state'],'total_centavos'=>(int)$saved['total_centavos']]);
} catch(Throwable $e) { http_response_code(422); header('Content-Type: application/json'); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
finally {
    // Delete only this harness's uniquely named, known fixture paths.
    foreach(glob($fixture.'/private/discount_ids/*')?:[] as $file) unlink($file);
    if(is_dir($fixture.'/private/discount_ids')) rmdir($fixture.'/private/discount_ids');
    if(is_dir($fixture.'/private')) rmdir($fixture.'/private');
    rmdir($fixture.'/www'); rmdir($fixture); $db->close();
}
