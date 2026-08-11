let products = [];
let vendors = [];
let uoms = [];
let currencies = [];

async function loadData() {

    try {

        // Products
        const productResponse =
            await fetch("../api/products.php");

        products =
            await productResponse.json();


        // Vendors
        const vendorResponse =
            await fetch("../api/vendors.php");

        vendors =
            await vendorResponse.json();


        // UOM
        const uomResponse =
            await fetch("../config/uom.json");

        const uomData =
            await uomResponse.json();

        uoms =
            uomData.uoms;


        // Currency
        const currencyResponse =
            await fetch("../config/currency.json");

        const currencyData =
            await currencyResponse.json();

        currencies =
            currencyData.currencies;

        loadCurrencies();

        // Load UI
        loadVendors();

function loadCurrencies() {

    const select =
        document.getElementById("currencySelect");


    currencies.forEach(currency => {

        const option =
            document.createElement("option");


        option.value =
            currency;


        option.textContent =
            currency;


        if (currency === "CNY") {

            option.selected = true;

        }


        select.appendChild(option);

    });


    updateCurrencyDisplay();

}
document
    .getElementById("currencySelect")
    .addEventListener("change", updateCurrencyDisplay);


function updateCurrencyDisplay() {

    const currency =
        document.getElementById("currencySelect").value;


    document.getElementById("currencyDisplay")
        .textContent =
        currency || "CNY";

}
    } catch (error) {

        console.error(
            "Failed to load data:",
            error
        );

    }

}


function loadVendors() {

    const select =
        document.getElementById("vendorSelect");

    vendors.forEach(vendor => {

        const option =
            document.createElement("option");

        option.value =
            vendor.Vendor_ID;

        option.textContent =
            vendor.Vendor_CompanyName;

        select.appendChild(option);

    });

}


function addItem() {

    const tbody =
        document.querySelector("#poItems tbody");

    const row =
        document.createElement("tr");


    row.innerHTML = `

        <td>

            <select class="productSelect"
                    onchange="productChanged(this)">

                <option value="">
                    Select Product
                </option>

                ${products.map(product => `

                    <option value="${product.Product_ID}">

                        ${product.Product_ID}

                    </option>

                `).join("")}

            </select>

        </td>


        <td>

            <input
                type="text"
                class="itemName"
                readonly
            >

        </td>


        <td>

            <input
                type="number"
                class="quantity"
                min="0"
                step="0.001"
                value="0"
                oninput="calculateRow(this)"
            >

        </td>


        <td>

            <select class="uomSelect">

                ${uoms.map(uom => `

                    <option value="${uom}"
                        ${uom === "PCS" ? "selected" : ""}>

                        ${uom}

                    </option>

                `).join("")}

            </select>

        </td>


        <td>

            <input
                type="number"
                class="unitPrice"
                min="0"
                step="0.01"
                value="0"
                oninput="calculateRow(this)"
            >

        </td>


        <td>

            <input
                type="text"
                class="subTotal"
                value="0.00"
                readonly
            >

        </td>


        <td>

            <input
                type="number"
                class="stockQty"
                min="0"
                step="0.001"
                value="0"
            >

        </td>


        <td>

            <button
                onclick="removeItem(this)">

                Remove

            </button>

        </td>

    `;


    tbody.appendChild(row);

}


function productChanged(select) {

    const row =
        select.closest("tr");

    const product =
        products.find(
            p => p.Product_ID === select.value
        );


    const itemName =
        row.querySelector(".itemName");


    if (product) {

        itemName.value =
            product.Product_Description;

    } else {

        itemName.value = "";

    }

}


function calculateRow(input) {

    const row =
        input.closest("tr");

    const quantity =
        parseFloat(
            row.querySelector(".quantity").value
        ) || 0;


    const unitPrice =
        parseFloat(
            row.querySelector(".unitPrice").value
        ) || 0;


    const subtotal =
        quantity * unitPrice;


    row.querySelector(".subTotal").value =
        subtotal.toFixed(2);


    calculateTotal();

}


function calculateTotal() {

    let totalAmount = 0;

    let totalQuantity = 0;


    document
        .querySelectorAll("#poItems tbody tr")
        .forEach(row => {

            const subtotal =
                parseFloat(
                    row.querySelector(".subTotal").value
                ) || 0;


            const quantity =
                parseFloat(
                    row.querySelector(".quantity").value
                ) || 0;


            totalAmount += subtotal;

            totalQuantity += quantity;

        });


    document.getElementById("poTotal")
        .textContent =
        totalAmount.toFixed(2);


    document.getElementById("totalQty")
        .textContent =
        totalQuantity;
}


function removeItem(button) {

    button
        .closest("tr")
        .remove();

    calculateTotal();

}


async function savePO() {

    const poId =
        document.getElementById("poNumber").value.trim();

    const poDate =
        document.getElementById("poDate").value;

    const vendorId =
        document.getElementById("vendorSelect").value;


    const rows =
        document.querySelectorAll("#poItems tbody tr");


    const items = [];


    rows.forEach(row => {

        const productId =
            row.querySelector(".productSelect").value;

        const quantity =
            parseFloat(
                row.querySelector(".quantity").value
            ) || 0;

        const uom =
            row.querySelector(".uomSelect").value;

        const unitPrice =
            parseFloat(
                row.querySelector(".unitPrice").value
            ) || 0;

        const stockQty =
            parseFloat(
                row.querySelector(".stockQty").value
            ) || 0;


        if (productId) {

            items.push({

                Product_ID: productId,

                Quantity: quantity,

                UOM: uom,

                Unit_Price: unitPrice,

                Stock_Qty: stockQty

            });

        }

    });


    if (!poId) {

        alert("Please enter PO Number.");

        return;

    }


    if (!poDate) {

        alert("Please select PO Date.");

        return;

    }


    if (!vendorId) {

        alert("Please select Vendor.");

        return;

    }


    if (items.length === 0) {

        alert("Please add at least one product.");

        return;

    }


    try {

        const response =
            await fetch("../api/po.php", {

                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

body: JSON.stringify({

    PO_ID: poId,

    Vendor_ID: vendorId,

    PO_Date: poDate,

    Ref_No:
        document.getElementById("refNo").value.trim(),

    Description:
        document.getElementById("description").value.trim(),

    Currency:
        document.getElementById("currencySelect").value,

    Prepared_Date:
        document.getElementById("preparedDate").value,

    Status: "DRAFT",

    Items: items

})

            });


        const result =
            await response.json();


        if (result.success) {

            alert(
                "PO saved successfully: "
                + result.PO_ID
            );

        } else {

            alert(
                "Failed to save PO: "
                + result.message
            );

        }


    } catch (error) {

        console.error(error);

        alert(
            "Unable to connect to server."
        );

    }

}


function submitPO() {

    alert("Submit PO - Backend will be added next.");

}

async function generatePOInfo() {

    try {

        const response =
            await fetch("../api/generate_po.php");

        const result =
            await response.json();

        if (!result.success) {

            alert(result.message);

            return;
        }

        document.getElementById("poNumber").value =
            result.PO_ID;

        document.getElementById("poDate").value =
            result.PO_Date;

    } catch (error) {

        console.error(
            "Failed to generate PO information:",
            error
        );

        alert(
            "Unable to generate PO number."
        );
    }
}
function toggleOthers() {

    const section =
        document.getElementById("othersSection");


    if (section.style.display === "none") {

        section.style.display = "block";

    } else {

        section.style.display = "none";

    }

}
loadData();
generatePOInfo();

function toggleSidebar() {

    const layout =
        document.querySelector(".layout");

    layout.classList.toggle(
        "sidebar-collapsed"
    );

}

/* ========================================================= */
/* EXPORT */
/* ========================================================= */

let selectedExportFormat = "";


function openExportMenu() {

    const modal =
        document.getElementById("exportModal");

    modal.style.display = "flex";


    document.getElementById(
        "exportFormatStep"
    ).style.display = "block";


    document.getElementById(
        "exportLanguageStep"
    ).style.display = "none";

}


function closeExportMenu() {

    document.getElementById(
        "exportModal"
    ).style.display = "none";

}


function selectExportFormat(format) {

    selectedExportFormat = format;


    document.getElementById(
        "exportFormatStep"
    ).style.display = "none";


    document.getElementById(
        "exportLanguageStep"
    ).style.display = "block";

}


function backToExportFormat() {

    document.getElementById(
        "exportFormatStep"
    ).style.display = "block";


    document.getElementById(
        "exportLanguageStep"
    ).style.display = "none";

}


function selectExportLanguage(language) {

    console.log(
        "Export Format:",
        selectedExportFormat
    );

    console.log(
        "Language:",
        language
    );


    if (selectedExportFormat === "pdf") {

        if (language === "zh") {

            alert("PDF 中文版本");

        } else {

            alert("PDF English Version");

        }

    }


    if (selectedExportFormat === "excel") {

        if (language === "zh") {

            alert("Excel 中文版本");

        } else {

            alert("Excel English Version");

        }

    }

}