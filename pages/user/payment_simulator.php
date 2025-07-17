<?php
session_start();

// Get payment parameters
$method = isset($_GET['method']) ? htmlspecialchars($_GET['method']) : '';
$orderId = isset($_GET['order_id']) ? htmlspecialchars($_GET['order_id']) : '';
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 0;

// Validate parameters
if (empty($method) || empty($orderId) || $amount <= 0) {
    header("Location: orders.php?error=Invalid payment parameters");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'success') {
        // Simulate successful payment
        header("Location: orders.php?payment_status=success&order_id=" . $orderId . "&method=" . $method);
        exit;
    } elseif ($action === 'failed') {
        // Simulate failed payment
        header("Location: orders.php?payment_status=failed&order_id=" . $orderId . "&method=" . $method);
        exit;
    } elseif ($action === 'cancel') {
        // Simulate cancelled payment
        header("Location: orders.php?payment_status=cancelled&order_id=" . $orderId . "&method=" . $method);
        exit;
    }
}

// Payment method configurations
$paymentMethods = [
    'paypal' => [
        'name' => 'PayPal',
        'icon' => 'fab fa-paypal',
        'color' => '#0070ba',
        'description' => 'Pay securely with your PayPal account'
    ],
    'gcash' => [
        'name' => 'GCash',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#007dff',
        'description' => 'Pay with your GCash mobile wallet'
    ],
    'paymaya' => [
        'name' => 'PayMaya',
        'icon' => 'fas fa-credit-card',
        'color' => '#00d4aa',
        'description' => 'Pay with PayMaya digital wallet'
    ],
    'grabpay' => [
        'name' => 'GrabPay',
        'icon' => 'fas fa-car',
        'color' => '#00b14f',
        'description' => 'Pay with your GrabPay wallet'
    ]
];

$currentMethod = isset($paymentMethods[$method]) ? $paymentMethods[$method] : null;

if (!$currentMethod) {
    header("Location: orders.php?error=Invalid payment method");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentMethod['name']; ?> Payment - LAB Jewels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --white: #ffffff;
            --gray: #6b7280;
            --light-gray: #e5e7eb;
            --dark-gray: #4b5563;
            --shadow-lg: 0 12px 24px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
            --gradient: linear-gradient(135deg, #2c2c2c, #1a1a1a);
            --primary-color: #1a1a1a;
            --background-color: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 0 10px -1px rgba(0, 0, 0, 0.1), 0 0 10px -1px rgba(0, 0, 0, 0.1);
            --success-color: #22c55e;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--background-color);
            color: var(--primary-color);
            font-family: 'Poppins', sans-serif;
            line-height: 1.7;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .payment-header {
            margin-bottom: 30px;
        }

        .payment-logo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: <?php echo $currentMethod['color']; ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: var(--white);
            font-size: 2.5rem;
        }

        .payment-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .payment-subtitle {
            font-size: 16px;
            color: var(--gray);
            margin-bottom: 30px;
        }

        .order-details {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: left;
        }

        .order-details h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            text-align: center;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .detail-row.total {
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
            margin-top: 15px;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
        }

        .payment-form {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--primary-color);
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: <?php echo $currentMethod['color']; ?>;
            box-shadow: 0 0 0 3px <?php echo $currentMethod['color']; ?>20;
        }

        .button {
            background-color: <?php echo $currentMethod['color']; ?>;
            color: var(--white);
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            opacity: 0.9;
        }

        .button.secondary {
            background-color: var(--gray);
        }

        .button.danger {
            background-color: var(--danger-color);
        }

        .security-info {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #065f46;
        }

        .security-info i {
            margin-right: 8px;
            color: #059669;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.active {
            display: block;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--light-gray);
            border-top: 4px solid <?php echo $currentMethod['color']; ?>;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .demo-notice {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #92400e;
        }

        .demo-notice i {
            margin-right: 8px;
            color: var(--warning-color);
        }

        @media (max-width: 480px) {
            .payment-container {
                padding: 30px 20px;
                margin: 20px;
            }

            .payment-title {
                font-size: 24px;
            }

            .order-details {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <div class="payment-logo">
                <i class="<?php echo $currentMethod['icon']; ?>"></i>
            </div>
            <h1 class="payment-title"><?php echo $currentMethod['name']; ?> Payment</h1>
            <p class="payment-subtitle"><?php echo $currentMethod['description']; ?></p>
        </div>

        <div class="demo-notice">
            <i class="fas fa-info-circle"></i>
            <strong>Demo Mode:</strong> This is a payment simulation. No real money will be charged.
        </div>

        <div class="order-details">
            <h3>Order Summary</h3>
            <div class="detail-row">
                <span>Order ID:</span>
                <span>#<?php echo substr($orderId, 6); ?></span>
            </div>
            <div class="detail-row">
                <span>Merchant:</span>
                <span>LAB Jewels</span>
            </div>
            <div class="detail-row">
                <span>Payment Method:</span>
                <span><?php echo $currentMethod['name']; ?></span>
            </div>
            <div class="detail-row total">
                <span>Total Amount:</span>
                <span>PHP <?php echo number_format($amount, 2); ?></span>
            </div>
        </div>

        <div id="paymentForm" class="payment-form">
            <?php if ($method === 'paypal'): ?>
                <div class="form-group">
                    <label for="email">PayPal Email:</label>
                    <input type="email" id="email" placeholder="your-email@example.com" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" placeholder="Enter your PayPal password" required>
                </div>
            <?php elseif ($method === 'gcash'): ?>
                <div class="form-group">
                    <label for="mobile">Mobile Number:</label>
                    <input type="tel" id="mobile" placeholder="09XX XXX XXXX" required>
                </div>
                <div class="form-group">
                    <label for="pin">GCash PIN:</label>
                    <input type="password" id="pin" placeholder="Enter your 4-digit PIN" maxlength="4" required>
                </div>
            <?php elseif ($method === 'paymaya'): ?>
                <div class="form-group">
                    <label for="mobile">Mobile Number:</label>
                    <input type="tel" id="mobile" placeholder="09XX XXX XXXX" required>
                </div>
                <div class="form-group">
                    <label for="pin">PayMaya PIN:</label>
                    <input type="password" id="pin" placeholder="Enter your 6-digit PIN" maxlength="6" required>
                </div>
            <?php elseif ($method === 'grabpay'): ?>
                <div class="form-group">
                    <label for="mobile">Mobile Number:</label>
                    <input type="tel" id="mobile" placeholder="09XX XXX XXXX" required>
                </div>
                <div class="form-group">
                    <label for="pin">GrabPay PIN:</label>
                    <input type="password" id="pin" placeholder="Enter your 6-digit PIN" maxlength="6" required>
                </div>
            <?php endif; ?>

            <form method="POST" id="actionForm">
                <button type="button" class="button" onclick="processPayment('success')">
                    <i class="fas fa-check"></i>
                    Pay PHP <?php echo number_format($amount, 2); ?>
                </button>
                
                <button type="button" class="button danger" onclick="processPayment('failed')">
                    <i class="fas fa-times"></i>
                    Simulate Payment Failure
                </button>
                
                <button type="button" class="button secondary" onclick="processPayment('cancel')">
                    <i class="fas fa-arrow-left"></i>
                    Cancel Payment
                </button>
                
                <input type="hidden" name="action" id="actionInput">
            </form>
        </div>

        <div id="loadingDiv" class="loading">
            <div class="spinner"></div>
            <p>Processing your payment...</p>
            <p style="font-size: 14px; color: var(--gray); margin-top: 10px;">
                Please do not close this window.
            </p>
        </div>

        <div class="security-info">
            <i class="fas fa-shield-alt"></i>
            Your payment information is secure and encrypted. This is a demo environment for testing purposes only.
        </div>
    </div>

    <script>
        function processPayment(action) {
            // Basic form validation
            const inputs = document.querySelectorAll('#paymentForm input[required]');
            let isValid = true;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    input.style.borderColor = 'var(--danger-color)';
                    isValid = false;
                } else {
                    input.style.borderColor = 'var(--border-color)';
                }
            });
            
            if (!isValid && action === 'success') {
                alert('Please fill in all required fields.');
                return;
            }
            
            // Show loading state
            document.getElementById('paymentForm').style.display = 'none';
            document.getElementById('loadingDiv').classList.add('active');
            
            // Set action and submit after delay to simulate processing
            setTimeout(() => {
                document.getElementById('actionInput').value = action;
                document.getElementById('actionForm').submit();
            }, 2000);
        }

        // Auto-format mobile number inputs
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 11) value = value.slice(0, 11);
                
                if (value.length >= 4) {
                    value = value.replace(/(\d{4})(\d{3})(\d{4})/, '$1 $2 $3');
                } else if (value.length >= 3) {
                    value = value.replace(/(\d{4})(\d{3})/, '$1 $2');
                }
                
                e.target.value = value;
            });
        });

        // Auto-format PIN inputs
        document.querySelectorAll('input[type="password"]').forEach(input => {
            if (input.id === 'pin') {
                input.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '');
                });
            }
        });

        // Prevent form submission on Enter key
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                processPayment('success');
            }
        });

        // Add loading animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.payment-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                container.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>