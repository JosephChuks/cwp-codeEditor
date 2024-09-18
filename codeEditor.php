<?php
if (!isset($_GET["filename"]) || !is_file($_GET["filename"])) {
    die("Invalid file specified.");
}
$filename = $_GET["filename"];
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $content = $_POST["content"];
    file_put_contents($filename, $content);
    echo "File saved successfully!";
    exit();
}
$fileContents = htmlspecialchars(
    file_get_contents($filename),
    ENT_QUOTES,
    "UTF-8"
);
$fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$languageModes = [
    "html" => "html",
    "php" => "php",
    "js" => "javascript",
    "css" => "css",
    "json" => "json",
    "sh" => "sh",
    "py" => "python",
];
$editorMode = isset($languageModes[$fileExtension])
    ? $languageModes[$fileExtension]
    : "html";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Code Editor - <?php echo basename($filename); ?></title>

    <!-- Include Ace Editor -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ace.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ext-language_tools.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    
    <!-- Include Prettier -->
    <script src="https://unpkg.com/prettier@2.7.1/standalone.js"></script>
    <script src="https://unpkg.com/prettier@2.7.1/parser-html.js"></script>
    <script src="https://unpkg.com/prettier@2.7.1/parser-babel.js"></script> <!-- For JavaScript -->
    <script src="https://unpkg.com/prettier@2.7.1/parser-postcss.js"></script> <!-- For CSS -->
    <script src="https://unpkg.com/@prettier/plugin-php@0.19.0/standalone.js"></script> <!-- PHP plugin -->
    <script src="https://unpkg.com/@prettier/plugin-php@0.19.0/parser-php.js"></script>
    <script src="https://unpkg.com/prettier@2.7.1/parser-markdown.js"></script>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: Arial, sans-serif;
        }

        #editor {
            position: absolute;
            top: 50px;
            bottom: 10px;
            left: 0;
            right: 0;
        }

        #controls {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50px;
            background-color: #333;
            color: white;
            padding: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        #controls button, #controls select {
            padding: 5px;
            font-size: 14px;
            margin-left: 5px;
            cursor: pointer;
        }

        #save-status {
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            text-align: center;
            display: none;
            z-index: 9999;
        }
        
        #saveBtn {
            background-color: #4CAF50;
            border: 1px solid #4CAF50;
            color: white;
            width: 100px;
        }
        
        #formatBtn {
            background-color: #4493f8;
            border: 1px solid #4493f8;
            color: white;
        }
        
        #spinner-container {
        display: flex;
        justify-content: center;
        align-items: center;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #fff; /* or any background */
        z-index: 9999;
    }
    .spinner {
        border: 4px solid rgba(0, 0, 0, 0.1);
        border-left-color: #000;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }
    
    </style>
</head>
<body>
    

<div id="controls">
    <div>
        <label for="theme">Theme:</label>
        <select id="theme" onchange="changeTheme(this.value)">
            <option value="monokai">Monokai</option>
            <option value="github">GitHub</option>
            <option value="tomorrow">Tomorrow</option>
            <option value="kuroir">Kuroir</option>
        </select>

       
        <select id="mode" onchange="changeMode(this.value)">
            <option value="html" <?php if ($fileExtension == "html") {
                echo "selected";
            } ?>>HTML</option>
            <option value="php" <?php if ($fileExtension == "php") {
                echo "selected";
            } ?>>PHP</option>
            <option value="javascript" <?php if ($fileExtension == "js") {
                echo "selected";
            } ?>>JavaScript</option>
            <option value="css" <?php if ($fileExtension == "css") {
                echo "selected";
            } ?>>CSS</option>
            <option value="json" <?php if ($fileExtension == "json") {
                echo "selected";
            } ?>>JSON</option>
            <option value="sh" <?php if ($fileExtension == "sh") {
                echo "selected";
            } ?>>Bash</option>
            <option value="python" <?php if ($fileExtension == "py") {
                echo "selected";
            } ?>>Python</option>
        </select>

      
        <select id="fontSize" onchange="changeFontSize(this.value)">
            <option value="12px">Font: 12px</option>
            <option value="14px" selected>Font: 14px</option>
            <option value="16px">Font: 16px</option>
            <option value="18px">Font: 18px</option>
            <option value="20px">Font: 20px</option>
        </select>
        
       
        <span><strong>FILE PATH: </strong><?= $filename ?></span>
    </div>
 

    <div>
         <button onclick="formatCode()" id="formatBtn"><i class="fa fa-code"></i>&nbsp;Format Code</button>
        <button onclick="editor.undo()"><i class="fa fa-undo" aria-hidden="true"></i>&nbsp;Undo</button>
        <button onclick="editor.redo()"><i class="fa fa-repeat" aria-hidden="true"></i>&nbsp;Redo</button>
        <button onclick="editor.execCommand('replace')"><i class="fa fa-search-plus" aria-hidden="true"></i>&nbsp;Search/Replace</button>
        <button onclick="saveFile()" id="saveBtn"><i class="fa fa-floppy-o" aria-hidden="true"></i>&nbsp;Save</button>
    </div>
</div>
<div id="spinner-container">
    <div class="spinner"></div>
</div>
<div id="editor" style="display:none;"><?php echo $fileContents; ?></div>

<div id="save-status">File Saved!</div>

<script>
    
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('spinner-container').style.display = 'none';
    document.getElementById('editor').style.display = 'block';
});

// Initialize Ace Editor
    var editor = ace.edit("editor");
    editor.setTheme("ace/theme/monokai");
    editor.session.setMode("ace/mode/<?php echo $editorMode; ?>");
    editor.setOptions({
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: true,
        fontSize: "14px",  
        wrap: true,
        highlightActiveLine: true,
        highlightSelectedWord: true,
        enableAutoIndent: true,
enableKeyboardAccessibility: true
    });


    function changeTheme(theme) {
        editor.setTheme("ace/theme/" + theme);
    }

    function changeMode(mode) {
        editor.session.setMode("ace/mode/" + mode);
    }

    function changeFontSize(fontSize) {
        editor.setOptions({
            fontSize: fontSize
        });
    }

    function saveFile() {
        var btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        var content = editor.getValue();
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "codeEditor.php?filename=<?php echo $filename; ?>", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                document.getElementById('save-status').style.display = 'block';
                setTimeout(function() {
                    document.getElementById('save-status').style.display = 'none';
                    btn.disabled = false;
        btn.textContent = 'Save';
                }, 1000);
            }
        };
        xhr.send("content=" + encodeURIComponent(content));
    }
    
    
    function formatCode() {
    const code = editor.getValue();
    let formattedCode;
    
    const mode = "<?= $fileExtension ?>";
    try {
        switch (mode) {
            case 'html':
                formattedCode = prettier.format(code, { parser: "html", plugins: prettierPlugins });
                break;
            case 'js':
                formattedCode = prettier.format(code, { parser: "babel", plugins: prettierPlugins });
                break;
            case 'css':
                formattedCode = prettier.format(code, { parser: "css", plugins: prettierPlugins });
                break;
            case 'php':
                formattedCode = prettier.format(code, { parser: "php", plugins: prettierPlugins });
                break;
            default:
                formattedCode = prettier.format(code, { parser: "html", plugins: prettierPlugins });
                break;
        }

        editor.setValue(formattedCode, -1);
    } catch (error) {
        console.error("Formatting error:", error);
        alert("Error while formatting the code.");
    }
}

</script>
</body>
</html>
