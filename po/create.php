<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Create Purchase Order</title>

    <link rel="stylesheet"
          href="../css/style.css">

</head>

<body>

<div class="layout">

    <!-- SIDEBAR -->

    <aside class="sidebar" id="sidebar">

        <div class="sidebar-top">

            <div class="logo">
                Purchasing
            </div>

            <button
                class="sidebar-toggle"
                onclick="toggleSidebar()"
                type="button"
            >
                ☰
            </button>

        </div>


        <nav>

            <a
                href="../index.php"
                class="nav-item"
            >
                <span class="nav-icon">⌂</span>
                <span class="nav-text">Home</span>
            </a>


            <a
                href="create.php"
                class="nav-item active"
            >
                <span class="nav-icon">📄</span>
                <span class="nav-text">Purchase Order</span>
            </a>


            <a
                href="#"
                class="nav-item"
            >
                <span class="nav-icon">📦</span>
                <span class="nav-text">GRN</span>
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->

    <div class="main">

    <!-- ===================================================== -->
    <!-- HEADER -->
    <!-- ===================================================== -->

    <div class="header">

        <h1>Create Purchase Order</h1>

        <p>Enter purchase order details</p>

    </div>


    <!-- ===================================================== -->
    <!-- PO INFORMATION -->
    <!-- ===================================================== -->

<div class="section">

    <div class="po-number-row">

        <div class="form-group">

            <label>PO Number</label>

            <input
                type="text"
                id="poNumber"
                readonly
            >

        </div>


        <div class="form-group">

            <label>PO Date</label>

            <input
                type="date"
                id="poDate"
                readonly
            >

        </div>

    </div>

</div>


<div class="section po-information">

    <div class="po-left">

        <div class="form-row">

            <label>Supplier</label>

            <select id="vendorSelect">

                <option value="">
                    Select Vendor
                </option>

            </select>

        </div>


        <div class="form-row">

            <label>Ref No</label>

            <input
                type="text"
                id="refNo"
                placeholder="Enter reference number"
            >

        </div>


        <div class="form-row">

            <label>Description</label>

            <textarea
                id="description"
                rows="3"
                placeholder="Enter description"
            ></textarea>

        </div>


        <div class="form-row">

            <label>Photo</label>

            <input
                type="file"
                id="poPhoto"
                accept="image/*"
            >

        </div>

    </div>


    <div class="po-right">

        <div class="form-row">

            <label>Currency</label>

            <select id="currencySelect">

            </select>

        </div>


        <div class="summary-item">

            <span>Amount</span>

            <strong>

                <span id="currencyDisplay">
                    CNY
                </span>

                <span id="poTotal">
                    0.00
                </span>

            </strong>

        </div>


        <div class="summary-item">

            <span>Total Qty</span>

            <strong id="totalQty">
                0
            </strong>

        </div>


        <br>


<div class="po-action-buttons">

    <button
        type="button"
        onclick="savePO()"
    >
        Save
    </button>

    <button
        type="button"
        onclick="openExportMenu()"
        class="export-button"
    >
        Export
    </button>

</div>

    </div>

</div>


    <!-- ===================================================== -->
    <!-- ITEMS -->
    <!-- ===================================================== -->

    <div class="section">

        <div class="section-header">

            <h2>Items</h2>

            <div>

                <button onclick="addItem()">
                    + Add Item
                </button>

                <button onclick="toggleOthers()">
                    Others
                </button>

            </div>

        </div>


        <br>


        <table id="poItems">

            <thead>

                <tr>

                    <th>Item Code</th>

                    <th>Item Name</th>

                    <th>Quantity</th>

                    <th>UOM</th>

                    <th>Unit Price</th>

                    <th>Sub Total</th>

                    <th>Stock Qty</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            </tbody>

        </table>

    </div>


    <br>


    <!-- ===================================================== -->
    <!-- OTHERS -->
    <!-- ===================================================== -->

    <div
        class="section"
        id="othersSection"
        style="display:none;"
    >

        <h2>Others</h2>

        <br>


        <div class="form-row">

            <label>Created By</label>

            <input
                type="text"
                id="createdBy"
                value=""
                readonly
            >

        </div>


        <br>


        <div class="form-row">

            <label>Prepared Date</label>

            <input
                type="date"
                id="preparedDate"
            >

        </div>

    </div>


    <br>

</div>
</div>

<!-- ===================================================== -->
<!-- EXPORT MODAL -->
<!-- ===================================================== -->

<div
    id="exportModal"
    class="export-modal"
    style="display:none;"
>

    <div class="export-box">

        <div class="export-header">

            <h2 id="exportTitle">
                Export Purchase Order
            </h2>

            <button
                type="button"
                class="export-close"
                onclick="closeExportMenu()"
            >
                ×
            </button>

        </div>


        <!-- FORMAT -->

        <div id="exportFormatStep">

            <p class="export-question">
                Select export format
            </p>


            <div class="export-options">

                <button
                    type="button"
                    onclick="selectExportFormat('pdf')"
                >
                    <span>📄</span>
                    PDF
                </button>


                <button
                    type="button"
                    onclick="selectExportFormat('excel')"
                >
                    <span>📊</span>
                    Excel
                </button>

            </div>

        </div>


        <!-- LANGUAGE -->

        <div
            id="exportLanguageStep"
            style="display:none;"
        >

            <p class="export-question">
                Select language
            </p>


            <div class="export-options">

                <button
                    type="button"
                    onclick="selectExportLanguage('zh')"
                >
                    中文
                </button>


                <button
                    type="button"
                    onclick="selectExportLanguage('en')"
                >
                    English
                </button>

            </div>


            <button
                type="button"
                class="export-back"
                onclick="backToExportFormat()"
            >
                ← Back
            </button>

        </div>

    </div>

</div>


<script src="../js/po.js"></script>

</body>

</html>