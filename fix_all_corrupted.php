<?php
$files = glob("c:/xampp/htdocs/SantaBeachClub-BookingSystem/frontend/*.php");
$files = array_merge($files, glob("c:/xampp/htdocs/SantaBeachClub-BookingSystem/backend/**/*.php"));

foreach ($files as $file) {
    if (!is_file($file)) continue;
    $raw = file_get_contents($file);
    
    // Replace all corrupted peso representations with standard PHP "PHP" or clean currency format
    // Common corruptions for peso: â‚±, â,±, â€šÂ±, \xc3\xa2\xe2\x80\x9a\xc2\xb1, \xe2\x82\xb1, ,
    $raw = preg_replace('/(\xEF\xBF\xBD,\xEF\xBF\xBD|â€šÂ±|â,±|â‚±|\xe2\x82\xb1)/u', 'PHP', $raw);
    $raw = str_replace(["\xEF\xBF\xBD,\xEF\xBF\xBD", "â€šÂ±", "â,±", "â‚±"], 'PHP', $raw);
    
    // Replace corrupted dots / bullets: A&middot;, Â·, Â &middot;, ?"
    $raw = str_replace(["A&middot;", "Â·", "Â &middot;"], "·", $raw);
    $raw = str_replace(["?\"", "—"], "-", $raw);
    $raw = str_replace("o\"", "", $raw);
    $raw = str_replace("+'", "→", $raw);
    
    file_put_contents($file, $raw);
}
echo "Cleaned all PHP files successfully.\n";