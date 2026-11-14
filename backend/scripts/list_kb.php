<?php
require __DIR__ . '/../../backend/config/db.php';
$res = $conn->query('SELECT id, category, question, language FROM chatbot_knowledge');
if ($res) {
    while ($r = $res->fetch_assoc()) {
        echo $r['id'] . ' | ' . $r['category'] . ' | ' . $r['language'] . ' | ' . $r['question'] . PHP_EOL;
    }
}
