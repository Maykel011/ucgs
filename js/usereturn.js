document.addEventListener("DOMContentLoaded", function () {
    const dropdownArrows = document.querySelectorAll(".arrow-icon");

    // Retrieve dropdown state from localStorage
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};

    dropdownArrows.forEach(arrow => {
        let parent = arrow.closest(".dropdown");
        let dropdownText = parent.querySelector(".text").innerText;

        // Apply saved state
        if (savedDropdownState[dropdownText]) {
            parent.classList.add("active");
        }

        arrow.addEventListener("click", function (event) {
            event.stopPropagation(); // Prevent triggering the parent link
            
            let parent = this.closest(".dropdown");
            parent.classList.toggle("active");

            // Save the state in localStorage
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });
});

// Profile Dropdown
document.addEventListener("DOMContentLoaded", function () {
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");

    userIcon.addEventListener("click", function (event) {
        event.stopPropagation(); // Prevent closing when clicking inside
        userDropdown.classList.toggle("show");
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", function (event) {
        if (!userIcon.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });
});

document.getElementById('returnForm').addEventListener('submit', function (event) {
    event.preventDefault(); // Prevent default form submission

    const formData = new FormData(this);

    fetch('ItemReturned.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                this.reset(); // Clear the form
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            alert('An error occurred while submitting your request. Please try again.');
        });
});

document.addEventListener("DOMContentLoaded", function () {
    fetch('UserTransaction.php?action=return')
        .then(response => response.json())
        .then(data => {
            const transactionTable = document.getElementById('transaction-table-body');
            transactionTable.innerHTML = '';
            data.forEach(transaction => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${transaction.date}</td>
                    <td>${transaction.details}</td>
                `;
                transactionTable.appendChild(row);
            });
        })
        .catch(error => console.error('Error fetching transaction history:', error));
});

document.addEventListener("DOMContentLoaded", function () {
    fetchApprovedRequests();
});

function fetchApprovedRequests() {
    fetch('UserItemReturned.php?action=fetch_approved_requests')
        .then(response => response.json())
        .then(data => {
            const approvedRequestsTable = document.getElementById('approved-requests-table-body');
            approvedRequestsTable.innerHTML = '';
            data.forEach(request => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${request.item_name}</td>
                    <td>${request.quantity}</td>
                    <td>${request.date_needed}</td>
                    <td>${request.return_date}</td>
                `;
                approvedRequestsTable.appendChild(row);
            });
        })
        .catch(error => console.error('Error fetching approved requests:', error));
}
