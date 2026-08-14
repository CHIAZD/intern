(async () => {
    const response = await fetch("api/login.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            username: "admin",
            password: "123456"
        })
    });

    console.log("HTTP STATUS:", response.status);

    const text = await response.text();

    console.log("SERVER RESPONSE:", text);
})();