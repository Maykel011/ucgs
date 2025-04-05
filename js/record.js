document.addEventListener("DOMContentLoaded", function() {
    // ====================
    // DROPDOWN FUNCTIONALITY
    // ====================
    const dropdownArrows = document.querySelectorAll(".arrow-icon");
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};

    dropdownArrows.forEach(arrow => {
        const parent = arrow.closest(".dropdown");
        const dropdownText = parent.querySelector(".text").innerText;

        // Apply saved state
        if (savedDropdownState[dropdownText]) {
            parent.classList.add("active");
        }

        arrow.addEventListener("click", function(event) {
            event.stopPropagation();
            parent.classList.toggle("active");
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });

    // ====================
    // USER DROPDOWN
    // ====================
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");

    userIcon.addEventListener("click", function(event) {
        event.stopPropagation();
        userDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function(event) {
        if (!userIcon.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });

    // ====================
    // TABLE FUNCTIONALITY
    // ====================
    const rowsPerPage = 10;
    let currentPage = 1;
    const tableBody = document.getElementById("item-table-body");
    const rows = Array.from(tableBody.querySelectorAll("tr:not(.no-results)"));
    let filteredRows = [...rows];
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);
    const deleteSelectedBtn = document.querySelector('.delete-selected-btn');

    // Initialize pagination
    showPage(currentPage);

    // ====================
    // SEARCH FUNCTIONALITY
    // ====================
    const searchBox = document.getElementById("search-box");
    const startDate = document.getElementById("start-date");
    const endDate = document.getElementById("end-date");

    // Automatic search on input
    searchBox.addEventListener("input", debounce(filterTable, 300));
    startDate.addEventListener("change", filterTable);
    endDate.addEventListener("change", filterTable);

    function filterTable() {
        const searchTerm = searchBox.value.toLowerCase();
        const startDateVal = startDate.value;
        const endDateVal = endDate.value;

        filteredRows = rows.filter(row => {
            const itemName = row.cells[1].textContent.toLowerCase(); // Item Name column
            const dateCell = row.cells[7].textContent; // Last Updated column
            
            // Check search term
            const matchesSearch = searchTerm === "" || itemName.includes(searchTerm);
            
            // Check date range
            let matchesDate = true;
            if (startDateVal && endDateVal) {
                matchesDate = dateCell >= startDateVal && dateCell <= endDateVal;
            }
            
            return matchesSearch && matchesDate;
        });

        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        currentPage = 1;
        showPage(currentPage);
    }

    function showPage(page) {
        // Clear any existing no-results message
        const noResultsRow = tableBody.querySelector(".no-results");
        if (noResultsRow) {
            noResultsRow.remove();
        }

        if (filteredRows.length === 0) {
            // Show no results message
            const noResultsRow = document.createElement("tr");
            noResultsRow.className = "no-results";
            noResultsRow.innerHTML = "<td colspan='12'>No matching items found</td>";
            tableBody.appendChild(noResultsRow);
            
            document.getElementById("page-number").innerText = "No results";
            document.getElementById("prev-btn").disabled = true;
            document.getElementById("next-btn").disabled = true;
            return;
        }

        // Hide all rows first
        rows.forEach(row => row.style.display = "none");
        
        // Show rows for current page
        const start = (page - 1) * rowsPerPage;
        const end = Math.min(start + rowsPerPage, filteredRows.length);
        
        for (let i = start; i < end; i++) {
            filteredRows[i].style.display = "table-row";
        }

        // Update pagination controls
        document.getElementById("page-number").innerText = `Page ${page} of ${totalPages}`;
        document.getElementById("prev-btn").disabled = page === 1;
        document.getElementById("next-btn").disabled = page === totalPages;

        // Update delete button state after pagination
        updateDeleteButtonState();
    }

    // ====================
    // PAGINATION
    // ====================
    window.nextPage = function() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    };

    window.prevPage = function() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    };

    // ====================
    // CHECKBOX & DELETE BUTTON FUNCTIONALITY
    // ====================
    const selectAllCheckbox = document.querySelector('.select-all');
    let selectItemCheckboxes = document.querySelectorAll('.select-item');

    // Function to update delete button state
    function updateDeleteButtonState() {
        const hasSelectedItems = document.querySelectorAll('.select-item:checked').length > 0;
        deleteSelectedBtn.disabled = !hasSelectedItems;
        
        // Update button style
        if (deleteSelectedBtn.disabled) {
            deleteSelectedBtn.style.opacity = '0.6';
            deleteSelectedBtn.style.cursor = 'not-allowed';
        } else {
            deleteSelectedBtn.style.opacity = '1';
            deleteSelectedBtn.style.cursor = 'pointer';
        }
    }

    // Initialize delete button state
    updateDeleteButtonState();

    // Select All functionality
    window.toggleSelectAll = function(checkbox) {
        const checkboxes = document.querySelectorAll(".select-item");
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        updateDeleteButtonState();
    };

    // Delete Selected functionality
    window.deleteSelected = function() {
        const selected = Array.from(document.querySelectorAll(".select-item:checked"));
        if (selected.length === 0) return;
        
        if (confirm(`Are you sure you want to delete ${selected.length} selected items?`)) {
            selected.forEach(checkbox => {
                const row = checkbox.closest("tr");
                row.remove();
                // Here you would typically make an AJAX call to delete from database
            });
            updateDeleteButtonState();
        }
    };

    // Event delegation for checkboxes (works for dynamically added rows)
    tableBody.addEventListener('change', function(e) {
        if (e.target.classList.contains('select-item')) {
            // Update "Select All" checkbox state
            const allChecked = document.querySelectorAll('.select-item:checked').length === 
                              document.querySelectorAll('.select-item').length;
            selectAllCheckbox.checked = allChecked;
            
            updateDeleteButtonState();
        }
    });

    // ====================
    // MODAL FUNCTIONALITY
    // ====================
    // Delete Modal
    const deleteModal = document.getElementById("deleteModal");
    const confirmDelete = document.getElementById("confirmDelete");
    const cancelDelete = document.getElementById("cancelDelete");
    let currentRow = null;

    window.openDeleteModal = function(button) {
        deleteModal.style.display = "block";
        currentRow = button.closest("tr");
    };

    confirmDelete.addEventListener("click", function() {
        if (currentRow) {
            currentRow.remove();
            updateDeleteButtonState();
            // Here you would typically also make an AJAX call to delete from database
        }
        deleteModal.style.display = "none";
    });

    cancelDelete.addEventListener("click", function() {
        deleteModal.style.display = "none";
    });

    // Create/Update Modals
    const createModal = document.getElementById("create-Item-modal");
    const updateModal = document.getElementById("updateModal");
    const cancelCreate = document.getElementById("cancel-btn");
    const cancelUpdate = document.getElementById("cancelUpdate");

    window.openCreateModal = function() {
        createModal.style.display = "block";
    };

    cancelCreate.addEventListener("click", function() {
        createModal.style.display = "none";
    });

    window.openUpdateModal = function(button) {
        const row = button.closest("tr");
        // Populate form fields with row data
        // Example:
        document.getElementById("update-item-name").value = row.cells[1].textContent;
        // ... populate other fields ...
        
        updateModal.style.display = "block";
    };

    cancelUpdate.addEventListener("click", function() {
        updateModal.style.display = "none";
    });

    // Close modals when clicking outside
    window.addEventListener("click", function(event) {
        if (event.target === deleteModal) deleteModal.style.display = "none";
        if (event.target === createModal) createModal.style.display = "none";
        if (event.target === updateModal) updateModal.style.display = "none";
    });

    // ====================
    // HELPER FUNCTIONS
    // ====================
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }
});