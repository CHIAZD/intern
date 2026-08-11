<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Purchasing System</title>

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="layout">

    <!-- Sidebar -->

    <aside class="sidebar">

        <div class="logo">
            Purchasing System
        </div>

        <a href="index.php" class="nav-item">
            Dashboard
        </a>

        <a href="#" class="nav-item">
            Products
        </a>

        <a href="#" class="nav-item">
            Vendors
        </a>

        <a href="#" class="nav-item">
            Purchase Orders
        </a>

    </aside>


    <!-- Main Content -->

    <main class="main">

        <div class="header">

            <h1>Dashboard</h1>

            <p>
                Purchasing System Overview
            </p>

        </div>


        <!-- Statistics -->

        <div class="cards">

            <div class="card">

                <div class="card-title">
                    Products
                </div>

                <div class="card-number" id="productCount">
                    -
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Vendors
                </div>

                <div class="card-number" id="vendorCount">
                    -
                </div>

            </div>

        </div>


        <!-- Quick Actions -->

        <div class="section">

            <h2>Quick Actions</h2>

            <br>

            <a href="#" class="button">
                Add Product
            </a>

            <a href="#" class="button">
                Add Vendor
            </a>

            <a href="#" class="button">
                Create Purchase Order
            </a>

        </div>

    </main>

</div>

<script src="js/app.js"></script>

</body>

</html>