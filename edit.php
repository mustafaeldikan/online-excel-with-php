<?php
$file_id = isset($_GET['fid']) ? intval($_GET['fid']) : 0;
$sheet_id = isset($_GET['sid']) ? intval($_GET['sid']) : 0;
$file_info = ['fname' => '', 'lastModified' => ''];
$cells = [];
$sheets = [];
$currentSheet = ['rows' => 10, 'cols' => 10]; // Default values
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
// Load file and sheet data
if ($file_id) {
    $conn = connect();
    $query = "SELECT * FROM files WHERE fid = $file_id";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        $file_info = $result->fetch_assoc();
        $cells = loadFromDatabase($file_id, $sheet_id);
    }
    $querySheets = "SELECT sid, sname, `rows`, cols FROM sheets WHERE fid = $file_id";
    $sheetsResult = $conn->query($querySheets);
    if ($sheetsResult->num_rows > 0) {
        while ($sheet = $sheetsResult->fetch_assoc()) {
            array_push($sheets, $sheet);
            if ($sheet_id == $sheet['sid']) {
                $currentSheet = $sheet;
            }
        }
    }
    $conn->close();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_sheet'])) {
        // Handle sheet creation
        $new_sheet_name = $_POST['sheet_name'];
        $conn = connect();
        $query = "INSERT INTO sheets (fid, sname,`rows`,cols) VALUES ($file_id, '$new_sheet_name','10','10')";
        if ($conn->query($query) === TRUE) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        $conn->close();
        exit;
    }

    if (isset($_POST['increase_row'])) {
        // Handle increasing row count
        $sheet_id = intval($_POST['sheet_id']);
        $clicked_row = intval($_POST['row_no']);
        $conn = connect();

        // Shift rows down starting from the clicked row
        $queryShiftRows = "UPDATE cell SET `row` = `row` + 1 WHERE sid = $sheet_id AND `row` > $clicked_row";
        if ($conn->query($queryShiftRows) !== TRUE) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            $conn->close();
            exit;
        }

        // Increment the row count for the sheet
        $queryIncrementRowCount = "UPDATE sheets SET `rows` = `rows` + 1 WHERE sid = $sheet_id";
        if ($conn->query($queryIncrementRowCount) === TRUE) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        $conn->close();
        exit;
    }
    if (isset($_POST['increase_col'])) {
        // Handle increasing row count
        $sheet_id = intval($_POST['sheet_id']);
        $clicked_col = intval($_POST['col_no']);
        $conn = connect();

        // Shift rows down starting from the clicked row
        $queryShiftCols = "UPDATE cell SET `col` = `col` + 1 WHERE sid = $sheet_id AND `col` > $clicked_col";
        if ($conn->query($queryShiftCols) !== TRUE) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            $conn->close();
            exit;
        }

        // Increment the row count for the sheet
        $queryIncrementColCount = "UPDATE sheets SET `cols` = `cols` + 1 WHERE sid = $sheet_id";
        if ($conn->query($queryIncrementColCount) === TRUE) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        $conn->close();
        exit;
    }
    if (isset($_POST['delete_row'])) {
        // Handle row deletion
        $sheet_id = intval($_POST['sheet_id']);
        $row_no = intval($_POST['row_no']);
        $conn = connect();

        // Delete rows where the row number matches the one to delete
        $queryDeleteRow = "DELETE FROM cell WHERE sid = $sheet_id AND `row` = $row_no";
        if ($conn->query($queryDeleteRow) !== TRUE) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            $conn->close();
            exit;
        }

        // Shift rows up starting from the row after the deleted row
        $queryShiftRows = "UPDATE cell SET `row` = `row` - 1 WHERE sid = $sheet_id AND `row` > $row_no";
        if ($conn->query($queryShiftRows) !== TRUE) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            $conn->close();
            exit;
        }

        // Decrement the row count for the sheet
        $queryDecrementRowCount = "UPDATE sheets SET `rows` = `rows` - 1 WHERE sid = $sheet_id";
        if ($conn->query($queryDecrementRowCount) === TRUE) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        $conn->close();
        exit;
    }

    if (isset($_POST['delete_col'])) {
        // Handle column deletion
        $sheet_id = intval($_POST['sheet_id']);
        $col_no = intval($_POST['col_no']);
        $conn = connect();

        // Delete column data from database
        $queryDeleteCol = "DELETE FROM cell WHERE sid = $sheet_id AND `col` = $col_no";
        if ($conn->query($queryDeleteCol) !== TRUE) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            $conn->close();
            exit;
        }

        // Shift columns left starting from the column after the deleted column
        $queryShiftCols = "UPDATE cell SET `col` = `col` - 1 WHERE sid = $sheet_id AND `col` > $col_no";
        if ($conn->query($queryShiftCols) !== TRUE) {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
            $conn->close();
            exit;
        }

        // Decrement the column count for the sheet
        $queryDecrementColCount = "UPDATE sheets SET `cols` = `cols` - 1 WHERE sid = $sheet_id";
        if ($conn->query($queryDecrementColCount) === TRUE) {
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
                        <li><a href="#" id="insert_row" style="display: block; padding: 8px; text-decoration: none; color: black;">Insert new row </a></li>
                        <li><a href="#" id="insert_col" style="display: block; padding: 8px; text-decoration: none; color: black;">Insert new col</a></li>
                        <li><a href="#" id="delete_row" style="display: block; padding: 8px; text-decoration: none; color: black;">Delete row </a></li>
                        <li><a href="#" id="delete_col" style="display: block; padding: 8px; text-decoration: none; color: black;">Delete col </a></li>

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
        <th class='file-info-header'  colspan='" . ($cols + 1) . "' style='text-align: left; padding-left: 10px;'><strong>File Name:</strong> " . htmlspecialchars($file_info['fname']) . "</th>
        </tr>";
    $html .= "<tr style='text-align: center'>
        <th class='file-info-header'  colspan='" . ($cols + 1) . "' style='text-align: left; padding-left: 10px;'><strong>Last Modified:</strong> " . htmlspecialchars($file_info['lastModified']) . "</th>
        </tr>";
    $html .= "<tr style='text-align: center'>
        <th class='file-info-header' ><a href='excel.php' style='color: blue'>&lt;=Back</a></th>";

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
            $focused = $j === 1 ? 'autofocus' : '';
            $html .= "<td><input type='text' $focused name='$cell_key' data-row='$i' data-col='$j' value='$value' onchange='updateCell(this)' style='width:100%' /></td>";
        }
        $html .= "</tr>";
    }

    $html .= "<tr><td><a href='#' onclick='createNewSheet()' style='color:green'>Sheets</a></td>";
    foreach ($sheets as $sheet) {
        $sname = $sheet['sname'];
        $sid = $sheet['sid'];
        $className = $sheet['sid'] == $sheet_id ? 'myColor' : '';
        $html .= "<td id='sheet_$sid' class='$className' style='text-align: center;'>
            <a href='edit.php?fid=$file_id&sid=$sid'  class='action-link' style='color: blue'>$sname</a>
        </td>";
    }

    return $html;
}

echo '
    ' . pageHeader("Excel File") . '
    <h2>Edit File fid: ' . htmlspecialchars($file_id) . '</h2>
    <form method="POST">
        <input type="hidden" name="file_id" value="' . htmlspecialchars($file_id) . '">
        <input type="hidden" name="file_name" value="' . htmlspecialchars($file_info['fname']) . '">
        <input type="hidden" name="last_modified" value="' . htmlspecialchars($file_info['lastModified']) . '">
        ' . excel($currentSheet['rows'], $currentSheet['cols'], $file_info, $cells, $sheets, $file_id, $sheet_id) . '
    </form>
    ' . pageFooter() . '
    <script>
        var clickedCell = {}
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
                clickedCell = {row:e.target.getAttribute("data-row")
                ,col:e.target.getAttribute("data-col")};
            } else {
                contextMenu.style.display = "none";
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target.closest("#context-menu")) return;
            contextMenu.style.display = "none";
            if(e.target.tagName === "INPUT"){
                currentInput = e.target;
            }
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

document.addEventListener("keydown", function(e) {
    if (!currentInput) return;
    let row = parseInt(currentInput.getAttribute("data-row"));
    let col = parseInt(currentInput.getAttribute("data-col"));
    
    switch(e.keyCode) {
        case 37:
            col--
            break;
        case 38:
            row--;
            break;
        case 39:
            col++;
            break;
        case 40:
            row++;
            break;
    }

    let nextInput = document.querySelector(`input[data-row="${row}"][data-col="${col}"]`);
    if (nextInput) {
        currentInput = nextInput
        nextInput.focus();
    } else {
        console.log("No input found at row " + row + ", col " + col);
    }
});



        document.getElementById("insert_row").addEventListener("click", function() {
            var fileId = ' . $file_id . ';
            var sheetId = ' . $sheet_id . ';
            var rowNo = clickedCell.row;

            fetch("", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    increase_row: true,
                    file_id: fileId,
                    sheet_id: sheetId,
                    row_no: rowNo

                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    location.reload();
                } else {
                    alert("Error increasing row count: " + data.message);
                }
            })
            .catch(error => {
                console.error("Error:", error);
        });
});



 document.getElementById("insert_col").addEventListener("click", function() {
            var fileId = ' . $file_id . ';
            var sheetId = ' . $sheet_id . ';
            var colNo = clickedCell.col;

            fetch("", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: new URLSearchParams({
                    increase_col: true,
                    file_id: fileId,
                    sheet_id: sheetId,
                    col_no: colNo

                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    location.reload();
                } else {
                    alert("Error increasing col count: " + data.message);
                }
            })
            .catch(error => {
                console.error("Error:", error);
        });
});

        document.getElementById("delete_row").addEventListener("click", function() {
    if (clickedCell.row) {
        var fileId = ' . $file_id . ';
        var sheetId = ' . $sheet_id . ';
        var rowNo = clickedCell.row;

        fetch("", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: new URLSearchParams({
                delete_row: true,
                file_id: fileId,
                sheet_id: sheetId,
                row_no: rowNo
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                location.reload();
            } else {
                alert("Error deleting row: " + data.message);
            }
        })
        .catch(error => {
            console.error("Error:", error);
        });
    }
    contextMenu.style.display = "none";
});


document.getElementById("delete_col").addEventListener("click", function() {
    if (clickedCell.col) {
        var fileId = ' . $file_id . ';
        var sheetId = ' . $sheet_id . ';
        var colNo = clickedCell.col;

        fetch("", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: new URLSearchParams({
                delete_col: true,
                file_id: fileId,
                sheet_id: sheetId,
                col_no: colNo
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                location.reload();
            } else {
                alert("Error deleting column: " + data.message);
            }
        })
        .catch(error => {
            console.error("Error:", error);
        });
    }
    contextMenu.style.display = "none";
});

document.addEventListener("DOMContentLoaded", function() {
    var sheetLinks = document.querySelectorAll(".myColor");

    if (sheetLinks.length > 0) {
        sheetLinks[0].classList.add("selected-sheet");
    }
    
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
                    confirm("Please Enter different name of the new sheet:");
                });
            }
        }

    </script>
';
