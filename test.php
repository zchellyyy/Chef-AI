<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo "Filipino Recipe Generator"; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        h1 {
            color: #d35400;
            margin-bottom: 20px;
        }
        .iframe-container {
            width: 100%;
            max-width: 800px;
            margin: 20px 0;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <h1><?php echo "Filipino Recipe Generator"; ?></h1>
    <div class="iframe-container">
        <?php
        $iframeSrc = "https://www.yeschat.ai/i/gpts-2OTolVLULz-Kain-na-A-Filipino-Recipe-Generator";
        echo '<iframe src="' . htmlspecialchars($iframeSrc) . '" 
                     width="800" 
                     height="500" 
                     style="max-width: 100%; border: none;"></iframe>';
        ?>
    </div>
    <p><?php echo "Explore delicious Filipino recipes with our interactive generator!"; ?></p>
</body>
</html>