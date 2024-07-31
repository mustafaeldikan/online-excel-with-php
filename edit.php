<?php
$rows = 10;
$cols = 10;
$json_file = 'data.json'; // Path to your JSON file

$file_id = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
$sheet_id = isset($_GET['sid']) ? intval($_GET['sid']) : 0;
$file_info = ['fname' => '', 'lastModified' => ''];
$cells = [];
$sheets = [];

if ($file_id) {
    $conn = connect();
    $query = "SELECT * FROM files WHERE fid = $file_id";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $file_info = $result->fetch_assoc();
        $cells = loadJsonData($file_id, $sheet_id);
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

function connect()
{
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "excel";

    $conn = new mysqli($servername, $username, $password, $dbname) or die("Connection failed: " . $conn->connect_error);
    return $conn;
}

function pageHeader($heading)
{
    return <<<ZZZZ
        <html><head><title>$heading</title></head><body>
                <link rel="stylesheet" href="style.css">

ZZZZ;
}

function pageFooter()
{
    return "</body></html>";
}

function loadJsonData($file_id, $sheet_id)
{
    global $json_file;
    $data = [];

    if (file_exists($json_file)) {
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);

        if ($data === null) {
            $data = [];
        }
    }

    foreach ($data as $entry) {
        if ($entry['file_id'] == $file_id) {
            $sheet_data = $entry['sheets'][$sheet_id] ?? [];
            return $sheet_data;
        }
    }
    return [];
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
            // Use null coalescing operator to avoid undefined key issue
            $value = isset($cells[$cell_key]) ? htmlspecialchars($cells[$cell_key]) : '';
            $html .= "<td><input type='text' name='$cell_key' data-row='$i' data-col='$j' value='$value' onchange='updateCell(this)' style='width:100%' /></td>";
        }
        $html .= "</tr>";
    }

    $html .= "<tr><td><strong>Sheets</strong></td>";
    foreach ($sheets as $sheet) {
        $sname = $sheet['sname'];
        $sid = $sheet['sid'];
        $html .= "<td style='text-align: center;'>
            <a href='edit.php?fid=$file_id' class='action-link' style='color: blue'>sheet0</a>
        </td>";
        $html .= "<td style='text-align: center;'>
            <a href='edit.php?fid=$file_id&sid=$sid' class='action-link' style='color: blue'>$sname</a>
        </td>";
    }
    $html .= "</tr></table>";

    return $html;
}


//json file operations
function saveToJsonFile($file_id, $sheet_id, $row, $col, $value, $file_name = '', $last_modified = '')
{
    global $json_file;
    $data = [];

    if (file_exists($json_file)) {
        $json_data = file_get_contents($json_file);
        $data = json_decode($json_data, true);

        if ($data === null) {
            $data = [];
        }
    }

    $found = false;
    foreach ($data as &$entry) {
        if ($entry['file_id'] == $file_id) {
            if (!isset($entry['sheets'][$sheet_id])) {
                $entry['sheets'][$sheet_id] = [];
            }
            $entry['sheets'][$sheet_id]["cell_{$row}_{$col}"] = $value;
            $entry['file_info'] = [
                'fname' => $file_name,
                'lastModified' => $last_modified
            ];
            $found = true;
            break;
        }
    }

    if (!$found) {
        $data[] = [
            'file_id' => $file_id,
            'file_info' => [
                'fname' => $file_name,
                'lastModified' => $last_modified
            ],
            'sheets' => [
                $sheet_id => ["cell_{$row}_{$col}" => $value]
            ]
        ];
    }

    file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT));
}




// Handle the POST request to update the JSON file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
    $sheet_id = isset($_POST['sheet_id']) ? intval($_POST['sheet_id']) : 0;
    $row = isset($_POST['row']) ? intval($_POST['row']) : 0;
    $col = isset($_POST['col']) ? intval($_POST['col']) : 0;
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    $file_name = isset($_POST['file_name']) ? $_POST['file_name'] : '';
    $last_modified = isset($_POST['last_modified']) ? $_POST['last_modified'] : '';

    saveToJsonFile($file_id, $sheet_id, $row, $col, $value, $file_name, $last_modified);

    echo json_encode(['status' => 'success']);
    exit;
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
    </script>

';
