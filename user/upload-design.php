<?php
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::requireAdmin();

$clientId = (int)$_POST['client_id'];

$baseOriginal = '../uploads/design/original/';
$basePreview  = '../uploads/design/preview/';

@mkdir($baseOriginal, 0777, true);
@mkdir($basePreview, 0777, true);

$previews = [];

foreach ($_FILES['design_files']['tmp_name'] as $i => $tmp) {

    $name = uniqid() . '_' . basename($_FILES['design_files']['name'][$i]);
    $originalPath = $baseOriginal . $name;

    move_uploaded_file($tmp, $originalPath);

    /* ===== PREVIEW ONLY IMAGE ===== */
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (in_array($ext, ['jpg','jpeg','png'])) {

        $img = ($ext === 'png')
            ? imagecreatefrompng($originalPath)
            : imagecreatefromjpeg($originalPath);

        $w = imagesx($img);
        $h = imagesy($img);

        $newW = 900;
        $newH = intval($h * ($newW / $w));

        $canvas = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($canvas, $img, 0,0,0,0, $newW,$newH,$w,$h);

        // watermark
        $textColor = imagecolorallocatealpha($canvas, 255,255,255,75);
        imagestring($canvas, 5, 20, $newH - 40, 'PREVIEW ONLY', $textColor);

        $previewName = 'preview_' . $name . '.jpg';
        imagejpeg($canvas, $basePreview . $previewName, 55); // LOW QUALITY

        imagedestroy($img);
        imagedestroy($canvas);

        $previews[] = 'uploads/design/preview/' . $previewName;
    }
}

DB::execute(
    "UPDATE clients SET design_result = ? WHERE id = ?",
    [
        json_encode([
            'original' => 'stored',
            'preview' => $previews,
            'uploaded_at' => date('Y-m-d H:i:s')
        ]),
        $clientId
    ]
);

header("Location: client-detail.php?id=$clientId&uploaded=1");
exit;