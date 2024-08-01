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

switch (isset($_GET['is']) ? $_GET['is'] : '') {
    case 'add_new_file':
        add_new_file($_GET['fname'], $_GET['lastModified']);
        break;
        // case 'excel':
        //     excel($_GET['rows'], $_GET['cols'],$_GET['file_info']);
        //     break;
    default:
        echo pageHeader("Student List");
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
        cell3.innerHTML = "<a href='edit.php?fid=" + body.data.fid + "' class='action-link'>Edit</a>";
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
        $body = [
            "message" => "File successfully added.",
            "data" => [
                "fid" => $fid,
                "fname" => $fname,
                "lastModified" => $lastModified,
            ]
        ];
        $query2 = "INSERT INTO sheets(sname, `rows`, cols, fid) VALUES ('sheet2', 10, 10, $fid)";
        $conn->query($query2);
        echo json_encode($body);
    } else {
        echo json_encode(["error" => "Insertion error: " . $conn->error]);
    }
}


function listele()
{
    global $conn;
    $query = 'SELECT * FROM files';

    echo "<style>
       
        td, th {
            border: 1px solid red;
            padding: 8px;
            text-align: center;
        }
    </style>
    <table id='file_name'>
        <tr>
            <td colspan='3' class='title-cell'>My drive</td>
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
            echo "<tr id='{$row['fid']}'>
                        <td>{$row['fname']}</td>
                        <td>{$row['lastModified']}</td>
                        <td><a href='edit.php?fid={$row['fid']}' class='action-link'>Edit</a> </td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='3'>0 results</td></tr>";
    }
    echo "</table>";
    $conn->close();
}