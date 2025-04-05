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

document.getElementById('requestForm').addEventListener('submit', function(event) {
    const itemId = document.getElementById('item-id').value;
    const quantity = parseInt(document.getElementById('quantity').value, 10);
    const dateNeeded = document.getElementById('date_needed').value;
    const returnDate = document.getElementById('return_date').value;

    if (!itemId || !quantity || !dateNeeded || !returnDate) {
        alert('Please fill out all required fields.');
        event.preventDefault();
        return;
    }

    if (quantity <= 0 || isNaN(quantity)) {
        alert('Quantity must be a positive number.');
        event.preventDefault();
        return;
    }

    if (new Date(dateNeeded) > new Date(returnDate)) {
        alert('Return date must be after the date needed.');
        event.preventDefault();
        return;
    }
});

document.getElementById('item-category').addEventListener('change', function () {
    const category = this.value;
    const itemDropdown = document.getElementById('item-id');

    // Clear existing options
    itemDropdown.innerHTML = '<option value="" disabled selected>Select an Item</option>';

    if (category) {
        fetch(`UserItemBorrow.php?item_category=${encodeURIComponent(category)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(items => {
                if (Array.isArray(items) && items.length > 0) {
                    items.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item.item_id;
                        option.textContent = item.item_name;
                        itemDropdown.appendChild(option);
                    });
                } else {
                    const option = document.createElement('option');
                    option.value = "";
                    option.disabled = true;
                    option.textContent = "No items available for this category";
                    itemDropdown.appendChild(option);
                }
            })
            .catch(error => {
                console.error('Error fetching items:', error);
                const option = document.createElement('option');
                option.value = "";
                option.disabled = true;
                option.textContent = "Error loading items. Please try again.";
                itemDropdown.appendChild(option);
            });
    }
});

