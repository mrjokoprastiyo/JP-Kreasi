<?php

require_once 'core/mailer.php';

$result = Mailer::send(
'jokoprastiyo1212@gmail.com',
'Testing Email',
'<h2>Email berhasil dikirim</h2>'
);

var_dump($result);