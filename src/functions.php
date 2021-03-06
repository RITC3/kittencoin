<?php 

function connect_to_db(){
	return new mysqli("localhost", "kittencoin", "KittyPasswordy0", "KittenCoin");
}

function check_user($conn, $username){
	$statement = $conn->prepare("SELECT * FROM users WHERE name = ?");
	$statement->bind_param("s", $username);
	$statement->execute();
	$result = $statement->get_result();

    if($result->num_rows > 0)
        return True; 
    else
        return False;
}

function lookup_user($conn, $id){
    if(is_numeric($id))
    {
        #$sql = "SELECT id, name FROM users WHERE `id` = $id";
        $statement = $conn->prepare("SELECT id, name FROM users WHERE `id` = ?");
		$statement->bind_param("i", $id);
    }
    else
    {
        #$sql = "SELECT id, name FROM users WHERE name = '".$id."'";
        $statement = $conn->prepare("SELECT id, name FROM users WHERE name = ?");
		$statement->bind_param("s", $id);
    }

    $statement->execute();
    $query = $statement->get_result();
    #$query = $conn->query($sql);
    $result['errors'] = "";
    if($statement->error)
    {
        #$result['error'] = $sql."<br>".$conn->error;
        $result['error'] = "There was a problem with your search criteria";
    }
    elseif($query->num_rows == 0){
        if (isset($result['error']))
            $result['error'] = $result['error']."<br>No user found";
        else
            $result['error'] = "No user found";
    }
    else
		$result = $query->fetch_assoc();

    return $result;
}

function register_user($conn, $username, $password, $admin = 0){
	//no XSS here
	$username = htmlentities($username);

    $pass = md5($password);
	$statement = $conn->prepare("INSERT INTO users (`name`, `password`, `admin` ) VALUES (?, ?, ?)");
	$statement->bind_param("ssi", $username, $pass, $admin);
	$statement->execute();

    if($statement->error)
        $result = "There was a problem registering.";
    else
        $result = $statement->insert_id;
    return $result;
}

function change_password($conn, $id, $pass){
    $password = md5($pass);
    $sql = "UPDATE users SET password='".$password."' WHERE id=$id";
    $conn->query($sql);
    if($conn->error){
        $result = $conn->error."<br>".$sql;
    }
    else{
        $result = "Successful";
    }
    return $result;
}

function change_username($conn, $id, $username){
    $sql = "UPDATE users SET name='".$username."' WHERE id=$id";
    $conn->query($sql);
    if($conn->error){
        $result = $conn->error."<br>".$sql;
    }
    else{
        $result = "Successful";
    }
    return $result;
}

function change_points($conn, $id, $points){
    $sql = "UPDATE users SET total=$points WHERE id=$id";
    $conn->query($sql);
    if($conn->error){
        $result = $conn->error."<br>".$sql;
    }
    else{
        $result = "Successful";
    }
    return $result;  
}

function login_user($conn, $username, $ptpass){
    $password = md5($ptpass);
	$statement = $conn->prepare("SELECT * FROM users WHERE name = ? AND password = ?");
	$statement->bind_param("ss", $username, $password);
	$statement->execute();
	$query = $statement->get_result();
    $error = $statement->error;

    if($statement->error){
    	return "Username/password combination is bad.";
    }

    if ($query->num_rows > 0) {
        $result = $query->fetch_array(MYSQLI_ASSOC);
        $query->free();
    }
    else
        $result = False;

    return $result;
}

function get_total($conn, $id){
    $sql = "SELECT total FROM users WHERE `id` = $id";
    $query = $conn->query($sql);
    if ($query->num_rows > 0) {
        $result = $query->fetch_array(MYSQLI_ASSOC);
        $query->free();
    }
    else{
        $result = False;
    }
    return $result;
}

function limiter($conn, $to, $from, $amount, $comment){
	
	#if xfer to admins, no limit
	if ($to == 1 || $to == 2){
		return False;
	}

	#if blue to red
	if ($from > 2 && $from < 11){
		#if amount is greater than 500, false
		if ($amount > 500){
			return True;
		}

		#no blue to blue
		if ($to > 2 && $to < 11){
			return True;	
		}

		#query for limiter
		$statement = $conn->prepare("select limiter from users where id = ?");
		$statement->bind_param("i", $from);
	    $statement->execute();      
		$result = $statement->get_result();

		$output = $result->fetch_all();
		
		$result = $output[0][0];

		#if the limit+amount>500, false
		$total = $amount+$result;
		
		if ($total > 500){
			return True;
		} else{
			#updating limiter in database
    		$statement = $conn->prepare("UPDATE users SET limiter=? WHERE id=?");
    		$statement->bind_param("ii", $total, $from);
    		$statement->execute();
    		$query = $statement->get_result();
		
			#3-10 is blue, 11-14 is re
			return False;
		}
	}

	return False;
}




function transfer($conn, $to, $from, $amount, $comment){
	//no XSS for the comment
	$comment = htmlentities($comment);

    # Create new transaction
    	
	$create_sql = "INSERT INTO transfers (`transfer_to`,`transfer_from`, `amount`, `comment`) VALUES ( $to, $from, $amount,'".$comment."')";
	$create_stmt = $conn->prepare("INSERT INTO transfers (transfer_to, transfer_from, amount, comment) VALUES (?, ?, ?, ?)");
	$create_stmt->bind_param("ssis", $to, $from, $amount, $comment);

    # Send KittenCoins to user
//    $to_sql = "UPDATE users SET total = (total + $amount) WHERE id = $to";
	$to_stmt = $conn->prepare("UPDATE users SET total = (total + ?) WHERE id = ?");
	$to_stmt->bind_param("is", $amount, $to);

    # Subtract sent Coins
//    $from_sql = "UPDATE users SET total = (total - $amount) WHERE id = $from";
	$from_stmt = $conn->prepare("UPDATE users SET total = (total - ?) WHERE id = ?");
	$from_stmt->bind_param("is", $amount, $from);
    
//    $transfer = $conn->query($create_sql);
	$create_stmt->execute();
    if ($create_stmt->error) {
        $errors[] = $create_stmt->error;
        $errors[] = $create_sql;
    }
    else{
        //$to_transfer = $conn->query($to_sql);
		$to_stmt->execute();
        if ($to_stmt->error) {
            $errors[] = $to_stmt->error;
        }

        //$from_sql = $conn->query($from_sql);
		$from_stmt->execute();
        if ($from_stmt->error) {
            $errors[] = $from_stmt->error;
        }
    }

    if(count($errors)>0){
        $result = implode("<br>", $errors);
        return $result;
    }
    else{
        return True;
    }
}

function get_transfers($conn, $id){
    $result = [];
    $sql = "SELECT * FROM transfers WHERE `transfer_to` = $id OR `transfer_from` = $id";
    $query = $conn->query($sql);
    if ($conn->error) {
        $result = "Retrieving transfers has failed.";
    }
    else{
        while ($data = $query->fetch_array(MYSQLI_ASSOC)){
            $result[] = $data;
        }
    }
    return $result;
}

function get_info($conn, $id){
    $sql = "SELECT name, total, id, admin FROM users WHERE `id` = $id";
    $query = $conn->query($sql);
    $error = $conn->error;
    if($error){
        $result['error'] = "There's been an error gathering information about the user.";
        return $result;
    }
    $result = $query->fetch_array(MYSQLI_ASSOC);
    $query->free();
    return $result;

}

function check_errors(){
    if (isset($_GET['error'])) {
        echo '<div class="row">
            <div class="small-4 columns"><p></p> </div>
            <div class="small-4 columns text-center error_msg">
            <span class="error_msg">';
            echo htmlentities(urldecode($_GET['error']));
            echo '</span>
            </div>
            <div class="small-4 columns"><p></p> </div>
        </div>';
    } 
}
function get_users($conn){
    $sql = "SELECT * FROM users";
    $query = $conn->query($sql);
    $users = [];
    while ($data = $query->fetch_array(MYSQLI_ASSOC)){
        $users[] = $data;
    }
    $query->free();
    return $users;
}

function total_coins($conn){
    $sql = "SELECT SUM(total) FROM users";
    $query = $conn->query($sql);
    $result =  $query->fetch_row();
    return $result[0];
}

?>
