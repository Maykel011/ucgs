document.addEventListener("DOMContentLoaded", function () {
    fetch("../admin/db_connection.php")
        .then(response => response.json())
        .then(user => {
            if (user.role) {
                document.querySelector(".admin-text").textContent = `${user.username} (${user.role})`;
            }
        })
        .catch(error => console.error("Error fetching user details:", error));
});
