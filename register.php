// Insert user data
$sql = "INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, 'user', 1)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $username, $email, $hashed_password); 