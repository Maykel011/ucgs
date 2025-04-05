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



document.addEventListener("DOMContentLoaded", function () {
    const rowsPerPage = 10;
    let currentPage = 1;
    const tableBody = document.getElementById("item-table-body");
    const rows = Array.from(tableBody.getElementsByTagName("tr"));
    let filteredRows = [...rows]; // Stores search results
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);

    function showPage(page) {
        if (filteredRows.length === 0) {
            tableBody.innerHTML = "<tr><td colspan='100%'>No results found</td></tr>";
            document.getElementById("page-number").innerText = "No results";
            document.getElementById("prev-btn").disabled = true;
            document.getElementById("next-btn").disabled = true;
            return;
        }

        // Hide all rows
        rows.forEach(row => (row.style.display = "none"));

        // Calculate the start and end index
        let start = (page - 1) * rowsPerPage;
        let end = start + rowsPerPage;

        // Show only the rows for this page
        filteredRows.slice(start, end).forEach(row => (row.style.display = "table-row"));

        // Update page number display
        document.getElementById("page-number").innerText = `Page ${page} of ${totalPages}`;

        // Enable/disable buttons based on page number
        document.getElementById("prev-btn").disabled = page === 1;
        document.getElementById("next-btn").disabled = page === totalPages;
    }

    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }

    function searchTable() {
        const query = document.getElementById("search-input").value.toLowerCase();
        
        // Filter rows based on search query
        filteredRows = rows.filter(row => 
            row.textContent.toLowerCase().includes(query)
        );

        // Reset pagination for new search results
        currentPage = 1;
        totalPages = Math.ceil(filteredRows.length / rowsPerPage);

        showPage(currentPage);
    }

    function resetSearch() {
        document.getElementById("search-input").value = "";
        filteredRows = [...rows]; // Restore all rows
        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        currentPage = 1;
        showPage(currentPage);
    }

    // Initial table setup
    showPage(currentPage);

    // Attach functions to window for button clicks
    window.nextPage = nextPage;
    window.prevPage = prevPage;
    window.searchTable = searchTable;
    window.resetSearch = resetSearch;
});

//item records delete
document.addEventListener("DOMContentLoaded", function () {
    const deleteModal = document.getElementById("deleteModal");
    const confirmDelete = document.getElementById("confirmDelete");
    const cancelDelete = document.getElementById("cancelDelete");
    let currentRow = null;

    // Open modal function
    window.openDeleteModal = function (button) {
        console.log("Delete button clicked!"); // Debugging
        deleteModal.style.display = "block"; 
        currentRow = button.closest("tr"); 
    };

    // Close modal when cancel is clicked
    cancelDelete.addEventListener("click", function () {
        deleteModal.style.display = "none";
    });

    // Delete row when confirmed
    confirmDelete.addEventListener("click", function () {
        if (currentRow) {
            currentRow.remove();
        }
        deleteModal.style.display = "none";
    });

    // Close modal when clicking outside
    window.onclick = function (event) {
        if (event.target === deleteModal) {
            deleteModal.style.display = "none";
        }
    };
});

// Create New Item Modal
document.addEventListener("DOMContentLoaded", function () {
    const createModal = document.getElementById("create-Item-modal");
    const updateModal = document.getElementById("updateModal");
    const cancelCreate = document.getElementById("cancel-btn");
    const cancelUpdate = document.getElementById("cancelUpdate");

    // Open Create Modal
    window.openCreateModal = function () {
        const createModal = document.getElementById("create-Item-modal"); // Corrected modal ID
        createModal.style.display = "block";
    };

    // Close Create Modal
    cancelCreate.addEventListener("click", function () {
        createModal.style.display = "none";
    });

    // Open Update Modal
    window.openUpdateModal = function (button) {
        updateModal.style.display = "block";
        // Populate update modal fields with data from the selected row
        const row = button.closest("tr");
        document.getElementById("update-item-no").value = row.cells[1].innerText;
        document.getElementById("update-item-name").value = row.cells[2].innerText;
        document.getElementById("update-description").value = row.cells[3].innerText;
        document.getElementById("update-quantity").value = row.cells[4].innerText;
        document.getElementById("update-unit").value = row.cells[5].innerText;
        document.getElementById("update-status").value = row.cells[6].innerText;
        document.getElementById("update-model-no").value = row.cells[8].innerText;
        document.getElementById("update-item-category").value = row.cells[9].innerText;
        document.getElementById("update-item-location").value = row.cells[10].innerText;
    };

    // Close Update Modal
    cancelUpdate.addEventListener("click", function () {
        updateModal.style.display = "none";
    });

    // Close modals when clicking outside
    window.onclick = function (event) {
        if (event.target === createModal) {
            createModal.style.display = "none";
        }
        if (event.target === updateModal) {
            updateModal.style.display = "none";
        }
    };
});

document.addEventListener("DOMContentLoaded", function () {
    // Select All Functionality
    const selectAllCheckbox = document.querySelector(".select-all");
    const itemCheckboxes = document.querySelectorAll(".select-item");

    window.toggleSelectAll = function (checkbox) {
        itemCheckboxes.forEach(itemCheckbox => {
            itemCheckbox.checked = checkbox.checked;
        });
    };

    // Delete Selected Functionality
    window.deleteSelected = function () {
        const selectedCheckboxes = Array.from(itemCheckboxes).filter(checkbox => checkbox.checked);
        if (selectedCheckboxes.length === 0) {
            alert("No items selected for deletion.");
            return;
        }

        if (confirm("Are you sure you want to delete the selected items?")) {
            selectedCheckboxes.forEach(checkbox => {
                const row = checkbox.closest("tr");
                row.remove();
            });
        }
    };
});




