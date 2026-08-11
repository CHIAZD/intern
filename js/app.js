async function loadDashboard() {

    try {

        const productResponse =
            await fetch("api/products.php");

        const products =
            await productResponse.json();


        const vendorResponse =
            await fetch("api/vendors.php");

        const vendors =
            await vendorResponse.json();


        document.getElementById("productCount")
            .textContent = products.length;

        document.getElementById("vendorCount")
            .textContent = vendors.length;


    } catch (error) {

        console.error("Failed to load dashboard:", error);

    }

}


loadDashboard();