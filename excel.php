<?php
$conn = connect();

function connect()
{
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "excel";

    $conn = new mysqli($servername, $username, $password, $dbname) or die("Connection failed: " . $conn->connect_error);
    return $conn;
}

$is = isset($_GET['is']) ? $_GET['is'] : '';
$sid = isset($_GET['sid']) ? intval($_GET['sid']) : 0;

switch ($is) {
    case 'add_new_file':
        add_new_file($_GET['fname'], $_GET['lastModified']);
        break;
    default:
        echo pageHeader("File List");
        listele();
        echo pageFooter();
}

function pageHeader($heading)
{
    return <<<ZZZZ
        <html><head><title>$heading</title>
        <link rel="stylesheet" href="style.css">
        <script>
            function addRow() {
                var fname = document.getElementById("fname").value;
                var lastModified = document.getElementById("lastModified").value;
                fetch('excel.php?is=add_new_file&fname=' + encodeURIComponent(fname) + '&lastModified=' + encodeURIComponent(lastModified))
                .then(res => res.json())
                .then(body => {
                    var table = document.getElementById("file_name").getElementsByTagName('tbody')[0];
                    var newRow = table.insertRow();
                    var cell1 = newRow.insertCell(0);
                    var cell2 = newRow.insertCell(1);
                    var cell3 = newRow.insertCell(2);
                    cell1.innerHTML = body.data.fname;
                    cell2.innerHTML = body.data.lastModified;
                    cell3.innerHTML = "<a href='edit.php?fid=" + body.data.fid + "&sid=" + body.data.sid + "' class='action-link'>Edit</a>";
                    document.getElementById("fname").value = "";
                    document.getElementById("lastModified").value = "";
                })
                .catch(err => {
                    console.log("Error:", err);
                });
            }
        </script>
        </head><body>
ZZZZ;
}

function pageFooter()
{
    return "</body></html>";
}

function add_new_file($fname, $lastModified)
{
    global $conn;
    $conn = connect();
    $fname = $conn->real_escape_string($fname);
    $lastModified = $conn->real_escape_string($lastModified);
    $query = "INSERT INTO files(fname, lastModified) VALUES ('$fname', CURRENT_TIMESTAMP)";
    if ($conn->query($query) === TRUE) {
        $fid = mysqli_insert_id($conn);
        $query2 = "INSERT INTO sheets(sname, `rows`, cols, fid) VALUES ('sheet1', 10, 10, $fid)";
        $conn->query($query2);
        $sid = mysqli_insert_id($conn);
        $body = [
            "message" => "File successfully added.",
            "data" => [
                "fid" => $fid,
                "fname" => $fname,
                "lastModified" => $lastModified,
                "sid" => $sid
            ]
        ];
        echo json_encode($body);
    } else {
        echo json_encode(["error" => "Insertion error: " . $conn->error]);
    }
}

function listele()
{
    global $conn;
    $query = 'SELECT f.fid, f.fname, f.lastModified, s.sid FROM files f LEFT JOIN sheets s ON f.fid = s.fid';

    echo "<style>
        td, th {
            border: 1px solid red;
            padding: 8px;
            text-align: center;
        }
    </style>
    <table id='file_name'>
        <tr>
            <td colspan='4' class='title-cell'>My drive</td>
        </tr>
        <tr class='header-row'>
            <td>File Name</td>
            <td>Last Modified</td>
            <td>Actions</td>
        </tr>
        <tr>
            <td><input type='text' id='fname' name='fname' required></td>
            <td><input type='date' id='lastModified' name='lastModified' required></td>
            <td><button class='action-button' onclick='addRow()'>Add</button></td>
        </tr>";

    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $editLink = "edit.php?fid={$row['fid']}&sid={$row['sid']}";
            echo "<tr id='{$row['fid']}'>
                    <td>{$row['fname']}</td>
                    <td>{$row['lastModified']}</td>
                    <td><a href='$editLink' class='action-link'>Edit</a></td>
                </tr>";
        }
    } else {
        echo "<tr><td colspan='4'>0 results</td></tr>";
    }
    echo "</table>";
    $conn->close();
}
?>
