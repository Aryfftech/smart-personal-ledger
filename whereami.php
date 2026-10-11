<?php echo "FOLDER: " . __DIR__ . "<br>"; echo "ADA EVALUATION: " . (strpos(file_get_contents(__DIR__ . "/layout.php"), "evaluation") !== false ? "YA" : "TIDAK"); ?>
