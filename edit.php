<?php
$rows = 10;
$cols = 10;
$json_file = 'data.json'; // Path to your JSON file

$file_id = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
$sheet_id = isset($_GET['sid']) ? intval($_GET['sid']) : 0;
$file_info = ['fname' => '', 'lastModified' => ''];
$cells = [];
$sheets = [];

// Connect to database
function connect()
{
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "excel";

    $conn = new mysqli($servername, $username, $password, $dbname) or die("Connection failed: " . $conn->connect_error);
    return $conn;
}

// Load file and sheet data
if ($file_id) {
    $conn = connect();
    $query = "SELECT * FROM files WHERE fid = $file_id";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $file_info = $result->fetch_assoc();
        $cells = loadFromDatabase($file_id, $sheet_id);
    }
    $querySheets = "SELECT sid, sname FROM sheets WHERE fid = $file_id";
    $sheetsResult = $conn->query($querySheets);
    if ($sheetsResult->num_rows > 0) {
        while ($sheet = $sheetsResult->fetch_assoc()) {
            array_push($sheets, $sheet);
        }
    }
    $conn->close();
}



// Load data from DataBase
function loadFromDatabase($file_id, $sheet_id)
{

    // Load from database
    $conn = connect();
    $query = "SELECT row, col, data FROM cell WHERE sid = $sheet_id";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $sheet_data = [];
        while ($row = $result->fetch_assoc()) {
            $rowNumber = $row['row'];
            $colNumber = $row['col'];
            $cellKey = "cell_{$rowNumber}_{$colNumber}";
            $sheet_data[$cellKey] = $row['data'];
        }
        return $sheet_data;
    }

    $conn->close();
    return [];
}



// Save data to database
function saveToDatabase($file_id, $sheet_id, $row, $col, $value)
{

    $conn = connect();
    $stmt = $conn->prepare("INSERT INTO cell (sid, row, col, data) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE data = ?");
    $stmt->bind_param("iiiss", $sheet_id, $row, $col, $value, $value);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}


// Handle POST request to update the JSON file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_sheet'])) {
        // Handle sheet creation
        $new_sheet_name = $_POST['sheet_name'];
        $conn = connect();
        $query = "INSERT INTO sheets (fid, sname) VALUES ($file_id, '$new_sheet_name')";
        if ($conn->query($query) === TRUE) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        $conn->close();
        exit;
    }

    $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
    $sheet_id = isset($_POST['sheet_id']) ? intval($_POST['sheet_id']) : 0;
    $row = isset($_POST['row']) ? intval($_POST['row']) : 0;
    $col = isset($_POST['col']) ? intval($_POST['col']) : 0;
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    $file_name = isset($_POST['file_name']) ? $_POST['file_name'] : '';
    $last_modified = isset($_POST['last_modified']) ? $_POST['last_modified'] : '';

    saveToDatabase($file_id, $sheet_id, $row, $col, $value);
    exit;
}

// Output the page
function pageHeader($heading)
{
    return <<<ZZZZ
        <html><head><title>$heading</title></head><body>
                <link rel="stylesheet" href="style.css">
                      <div id="context-menu" style="display: none; position: absolute; background-color: white; border: 1px solid #ccc; z-index: 1000;">
                    <ul style="list-style: none; margin: 0; padding: 0;">
                        <li><a href="#" id="cut-cell" style="display: block; padding: 8px; text-decoration: none; color: black;">Cut</a></li>
                        <li><a href="#" id="copy-cell" style="display: block; padding: 8px; text-decoration: none; color: black;">Copy</a></li>
                        <li><a href="#" id="paste-cell" style="display: block; padding: 8px; text-decoration: none; color: black;">Paste</a></li>
                        <li id="paste-special">
                            <a href="#" style="display: block; padding: 8px; text-decoration: none; color: black;">Paste Special</a>
                            <ul id="paste-special-menu" style="display: none; position: absolute; left: 100%; top: 0; background-color: white; border: 1px solid #ccc; list-style: none; padding: 0; margin: 0;">
                                <li><a href="#" id="Element1" style="display: block; padding: 8px; text-decoration: none; color: black;">Element1</a></li>
                                <li><a href="#" id="Element2" style="display: block; padding: 8px; text-decoration: none; color: black;">Element2</a></li>
                                <li><a href="#" id="Element3" style="display: block; padding: 8px; text-decoration: none; color: black;">Element3</a></li>

                            </ul>
                        </li>
                    </ul>
                </div>
ZZZZ;
}

function pageFooter()
{
    return "</body></html>";
}

function excel($rows, $cols, $file_info, $cells, $sheets, $file_id, $sheet_id)
{
    $html = "<table border='1' style='width:75%'>";
    $html .= "<tr style='text-align: center'>
        <th colspan='" . ($cols + 1) . "' style='text-align: left; padding-left: 10px;'><strong>File Name:</strong> " . htmlspecialchars($file_info['fname']) . "</th>
        </tr>";
    $html .= "<tr style='text-align: center'>
        <th colspan='" . ($cols + 1) . "' style='text-align: left; padding-left: 10px;'><strong>Last Modified:</strong> " . htmlspecialchars($file_info['lastModified']) . "</th>
        </tr>";
    $html .= "<tr style='text-align: center'>
        <th><a href='excel.php' class='action-link' style='color: blue'>&lt;=Back</a></th>";

    for ($j = 1; $j <= $cols; $j++) {
        $html .= "<th>" . chr(64 + $j) . "</th>"; // Column letters
    }

    $html .= "</tr>";

    for ($i = 1; $i <= $rows; $i++) {
        $html .= "<tr>";
        $html .= "<td style='text-align: center;'>$i</td>";

        for ($j = 1; $j <= $cols; $j++) {
            $cell_key = "cell_{$i}_{$j}";
            $value = isset($cells[$cell_key]) ? htmlspecialchars($cells[$cell_key]) : '';
            $html .= "<td><input type='text' name='$cell_key' data-row='$i' data-col='$j' value='$value' onchange='updateCell(this)' style='width:100%' /></td>";
        }
        $html .= "</tr>";
    }

    $html .= "<tr><td><a href='#' onclick='createNewSheet()'>Sheets</a></td>";
    foreach ($sheets as $sheet) {
        $sname = $sheet['sname'];
        $sid = $sheet['sid'];
        $html .= "<td style='text-align: center;'>
            <a href='edit.php?fid=$file_id&sid=$sid' class='action-link' style='color: blue'>$sname</a>
        </td>";
    }
    //$html .= "<td><a href='#' onclick='createNewSheet()' class='action-link' style='color: blue'>+ New Sheet</a></td></tr></table>";

    return $html;
}

echo '
    ' . pageHeader("Edit File") . '
    <h2>Edit File fid: ' . htmlspecialchars($file_id) . '</h2>
    <form method="POST">
        <input type="hidden" name="file_id" value="' . htmlspecialchars($file_id) . '">
        <input type="hidden" name="file_name" value="' . htmlspecialchars($file_info['fname']) . '">
        <input type="hidden" name="last_modified" value="' . htmlspecialchars($file_info['lastModified']) . '">
        ' . excel($rows, $cols, $file_info, $cells, $sheets, $file_id, $sheet_id) . '
    </form>
    ' . pageFooter() . '
    <script>
        var clipboard = ""; // Variable to store copied data
        var contextMenu = document.getElementById("context-menu");
        var currentInput = null;

        document.addEventListener("contextmenu", function(e) {
            e.preventDefault();
            if (e.target.tagName === "INPUT") {
                currentInput = e.target;
                var x = e.clientX;
                var y = e.clientY;
                contextMenu.style.left = x + "px";
                contextMenu.style.top = y + "px";
                contextMenu.style.display = "block";
            } else {
                contextMenu.style.display = "none";
            }
        });

        document.getElementById("paste-special").addEventListener("mouseover", function(){
            let menu = document.getElementById("paste-special-menu")
            menu.style.display = "block"
        })

        document.getElementById("paste-special").addEventListener("mouseleave", function(){
            let menu = document.getElementById("paste-special-menu")
            menu.style.display = "none";
        })

        document.addEventListener("click", function(e) {
            if (e.target.closest("#context-menu")) return;
            contextMenu.style.display = "none";
        });

            document.getElementById("cut-cell").addEventListener("click", function() {
            if (currentInput) {
                navigator.clipboard.writeText(currentInput.value)
                currentInput.value = "";
                updateCell(currentInput); // Update the cell in the backend
            }
            contextMenu.style.display = "none";
        });

        document.getElementById("copy-cell").addEventListener("click", function() {
            if (currentInput) {
                console.log(currentInput)
                navigator.clipboard.writeText(currentInput.value)
            }
            contextMenu.style.display = "none";
        });

        document.getElementById("paste-cell").addEventListener("click", function() {
            if (currentInput) {
                navigator.clipboard.readText().then(text => {
                    currentInput.value = text;
                    updateCell(currentInput); // Update the cell in the backend
                })
            }
            contextMenu.style.display = "none";
        });

    

        function updateCell(input) {
            var row = input.getAttribute("data-row");
            var col = input.getAttribute("data-col");
            var value = input.value;
            var fileId = ' . $file_id . ';
            var sheetId = ' . $sheet_id . ';
            var fileName = document.querySelector("input[name=file_name]").value;
            var lastModified = document.querySelector("input[name=last_modified]").value;

            fetch("", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    file_id: fileId,
                    row: row,
                    col: col,
                    value: value,
                    sheet_id: sheetId,
                    file_name: fileName,
                    last_modified: lastModified
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log("Update successful:", data);
            })
            .catch(error => {
                console.error("Error updating cell:", error);
            });
        }

        function createNewSheet() {
            var fileId = ' . $file_id . ';
            var sheetName = prompt("Enter the name of the new sheet:");

            if (sheetName) {
                fetch("", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: new URLSearchParams({
                        create_sheet: true,
                        file_id: fileId,
                        sheet_name: sheetName
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success") {
                        location.reload();
                    } else {
                        alert("Error creating sheet: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                });
            }
        }
    </script>
';
