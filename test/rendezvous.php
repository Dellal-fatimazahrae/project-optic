<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'opti';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$error = '';
$success = '';

// Get available time slots
function getAvailableTimeSlots($date) {
    $timeSlots = [
        '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
        '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00'
    ];
    return $timeSlots;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $telephone = trim($_POST['telephone']);
    $date_rdv = $_POST['date_rdv'];
    $heure_rdv = $_POST['heure_rdv'];
    $type_rdv = $_POST['type_rdv'];
    $notes = trim($_POST['notes']);
    
    // Validation
    if (empty($nom) || empty($prenom) || empty($email) || empty($telephone) || empty($date_rdv) || empty($heure_rdv) || empty($type_rdv)) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($date_rdv) < strtotime('tomorrow')) {
        $error = 'Please select a date from tomorrow onwards.';
    } else {
        // Check if time slot is already booked
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous WHERE date_rdv = ? AND heure_rdv = ? AND statut != 'cancelled'");
        $stmt->execute([$date_rdv, $heure_rdv]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $error = 'This time slot is already booked. Please choose another time.';
        } else {
            // Book the appointment
            $stmt = $pdo->prepare("INSERT INTO rendezvous (nom, prenom, email, telephone, date_rdv, heure_rdv, type_rdv, notes, statut, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            
            try {
                $stmt->execute([$nom, $prenom, $email, $telephone, $date_rdv, $heure_rdv, $type_rdv, $notes]);
                $success = 'Your appointment has been booked successfully! We will contact you to confirm.';
                
                // Clear form data
                $_POST = array();
            } catch(PDOException $e) {
                $error = 'Booking failed. Please try again.';
            }
        }
    }
}

$timeSlots = getAvailableTimeSlots(date('Y-m-d'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - OpticLook</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .appointment-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .appointment-header {
            background: #2d3748;
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .logo-icon {
            margin-right: 0.5rem;
        }

        .appointment-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .appointment-header p {
            color: #e2e8f0;
            font-size: 1rem;
        }

        .appointment-form {
            padding: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }

        .required {
            color: #e53e3e;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f7fafc;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #2d3748;
            background: white;
            box-shadow: 0 0 0 3px rgba(45, 55, 72, 0.1);
        }

        .form-select {
            cursor: pointer;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .time-slot {
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f7fafc;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .time-slot:hover {
            border-color: #2d3748;
            background: #edf2f7;
        }

        .time-slot.selected {
            background: #2d3748;
            color: white;
            border-color: #2d3748;
        }

        .time-slot.unavailable {
            background: #fed7d7;
            color: #9b2c2c;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .service-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .service-option {
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f7fafc;
        }

        .service-option:hover {
            border-color: #2d3748;
            background: #edf2f7;
        }

        .service-option.selected {
            background: #2d3748;
            color: white;
            border-color: #2d3748;
        }

        .service-option h4 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .service-option p {
            font-size: 0.875rem;
            opacity: 0.8;
        }

        .btn-book {
            width: 100%;
            background: #2d3748;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-book:hover {
            background: #1a202c;
            transform: translateY(-1px);
        }

        .btn-book:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-danger {
            background: #fed7d7;
            color: #9b2c2c;
            border: 1px solid #feb2b2;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            color: #2d3748;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .back-home:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .back-home svg {
            margin-right: 0.5rem;
        }

        .info-box {
            background: #edf2f7;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .info-box h3 {
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .info-box ul {
            list-style: none;
            padding-left: 0;
        }

        .info-box li {
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
            color: #4a5568;
        }

        .info-box li::before {
            content: '✓';
            color: #22543d;
            font-weight: bold;
            margin-right: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .service-options {
                grid-template-columns: 1fr;
            }
            
            .time-slots {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .appointment-form {
                padding: 1.5rem;
            }
            
            .appointment-header {
                padding: 1.5rem;
            }
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-home">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5"></path>
                <path d="M12 19l-7-7 7-7"></path>
            </svg>
            Back to Home
        </a>

        <div class="appointment-container">
            <div class="appointment-header">
                <div class="logo">
                    <svg class="logo-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12c0 6 4 10 10 10s10-4 10-10S18 6 12 6 2 6 2 12z"/>
                        <path d="M8 12h8"/>
                        <path d="M12 8v8"/>
                    </svg>
                    OpticLook
                </div>
                <h1>Book an Appointment</h1>
                <p>Schedule your eye exam or consultation with our expert optometrists</p>
            </div>

            <div class="appointment-form">
                <div class="info-box">
                    <h3>What to expect during your visit:</h3>
                    <ul>
                        <li>Comprehensive eye examination</li>
                        <li>Professional consultation on eyewear options</li>
                        <li>Try on our exclusive collection</li>
                        <li>Expert fitting and adjustments</li>
                        <li>Follow-up care and support</li>
                    </ul>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="appointmentForm">
                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3 class="section-title">Personal Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nom">Last Name <span class="required">*</span></label>
                                <input type="text" id="nom" name="nom" class="form-control" 
                                       placeholder="Enter your last name" 
                                       value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>" 
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="prenom">First Name <span class="required">*</span></label>
                                <input type="text" id="prenom" name="prenom" class="form-control" 
                                       placeholder="Enter your first name" 
                                       value="<?php echo isset($_POST['prenom']) ? htmlspecialchars($_POST['prenom']) : ''; ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       placeholder="Enter your email address" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="telephone">Phone Number <span class="required">*</span></label>
                                <input type="tel" id="telephone" name="telephone" class="form-control" 
                                       placeholder="Enter your phone number" 
                                       value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>" 
                                       required>
                            </div>
                        </div>
                    </div>

                    <!-- Appointment Details -->
                    <div class="form-section">
                        <h3 class="section-title">Appointment Details</h3>
                        
                        <div class="form-group">
                            <label for="type_rdv">Service Type <span class="required">*</span></label>
                            <div class="service-options">
                                <div class="service-option" data-value="eye_exam">
                                    <h4>Eye Examination</h4>
                                    <p>Complete eye health check and vision test</p>
                                </div>
                                <div class="service-option" data-value="consultation">
                                    <h4>Eyewear Consultation</h4>
                                    <p>Professional advice on frames and lenses</p>
                                </div>
                                <div class="service-option" data-value="fitting">
                                    <h4>Frame Fitting</h4>
                                    <p>Adjustment and fitting of your eyewear</p>
                                </div>
                                <div class="service-option" data-value="repair">
                                    <h4>Repair Service</h4>
                                    <p>Fix and maintenance of your glasses</p>
                                </div>
                            </div>
                            <input type="hidden" id="type_rdv" name="type_rdv" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_rdv">Preferred Date <span class="required">*</span></label>
                                <input type="date" id="date_rdv" name="date_rdv" class="form-control" 
                                       min="<?php echo date('Y-m-d', strtotime('tomorrow')); ?>"
                                       value="<?php echo isset($_POST['date_rdv']) ? $_POST['date_rdv'] : ''; ?>" 
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="heure_rdv">Preferred Time <span class="required">*</span></label>
                                <div class="time-slots" id="timeSlots">
                                    <?php foreach ($timeSlots as $time): ?>
                                        <div class="time-slot" data-time="<?php echo $time; ?>">
                                            <?php echo $time; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" id="heure_rdv" name="heure_rdv" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">Additional Notes</label>
                            <textarea id="notes" name="notes" class="form-control" 
                                      placeholder="Any specific requirements or notes for your appointment..." 
                                      rows="4"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn-book">Book Appointment</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Service type selection
            const serviceOptions = document.querySelectorAll('.service-option');
            const typeInput = document.getElementById('type_rdv');
            
            serviceOptions.forEach(option => {
                option.addEventListener('click', function() {
                    serviceOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    typeInput.value = this.getAttribute('data-value');
                });
            });
            
            // Time slot selection
            const timeSlots = document.querySelectorAll('.time-slot');
            const timeInput = document.getElementById('heure_rdv');
            
            timeSlots.forEach(slot => {
                slot.addEventListener('click', function() {
                    if (!this.classList.contains('unavailable')) {
                        timeSlots.forEach(s => s.classList.remove('selected'));
                        this.classList.add('selected');
                        timeInput.value = this.getAttribute('data-time');
                    }
                });
            });
            
            // Date change handler - check availability
            const dateInput = document.getElementById('date_rdv');
            dateInput.addEventListener('change', function() {
                // Here you would typically make an AJAX call to check availability
                // For now, we'll simulate some unavailable slots
                timeSlots.forEach(slot => {
                    slot.classList.remove('unavailable', 'selected');
                    // Simulate some random unavailable slots
                    if (Math.random() < 0.2) {
                        slot.classList.add('unavailable');
                    }
                });
                timeInput.value = '';
            });
            
            // Form validation
            const form = document.getElementById('appointmentForm');
            form.addEventListener('submit', function(e) {
                if (!typeInput.value) {
                    e.preventDefault();
                    alert('Please select a service type.');
                    return;
                }
                
                if (!timeInput.value) {
                    e.preventDefault();
                    alert('Please select a time slot.');
                    return;
                }
            });
            
            // Auto-populate form if user is logged in (if session data is available)
            <?php if (isset($_SESSION['user_name'])): ?>
                const nameParts = "<?php echo $_SESSION['user_name']; ?>".split(' ');
                if (nameParts.length >= 2) {
                    document.getElementById('nom').value = nameParts[0];
                    document.getElementById('prenom').value = nameParts.slice(1).join(' ');
                }
                document.getElementById('email').value = "<?php echo $_SESSION['user_email']; ?>";
            <?php endif; ?>
        });
    </script>
</body>
</html>