document.addEventListener("DOMContentLoaded", function () {
    const dropdownArrows = document.querySelectorAll(".arrow-icon");
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");
    const tableBody = document.getElementById("item-table-body");
    const rowsPerPage = 10; // Limit rows per page to 10
    let currentPage = 1;
    let rows = Array.from(tableBody.getElementsByTagName("tr"));
    let filteredRows = [...rows];
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);

    // Dropdown state management
    const savedDropdownState = JSON.parse(localStorage.getItem("dropdownState")) || {};
    dropdownArrows.forEach(arrow => {
        const parent = arrow.closest(".dropdown");
        const dropdownText = parent.querySelector(".text").innerText;

        if (savedDropdownState[dropdownText]) {
            parent.classList.add("active");
        }

        arrow.addEventListener("click", function (event) {
            event.stopPropagation();
            parent.classList.toggle("active");
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });

    // Profile dropdown toggle
    userIcon.addEventListener("click", function (event) {
        event.stopPropagation();
        userDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {
        if (!userIcon.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });

    // Pagination logic
    function showPage(page) {
        if (filteredRows.length === 0) {
            tableBody.innerHTML = "<tr><td colspan='100%'>No results found</td></tr>";
            updatePaginationDisplay("No results", true, true);
            return;
        }

        rows.forEach(row => (row.style.display = "none"));
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        filteredRows.slice(start, end).forEach(row => (row.style.display = "table-row"));

        updatePaginationDisplay(`Page ${page} of ${totalPages}`, page === 1, page === totalPages);
    }

    function updatePaginationDisplay(pageText, disablePrev, disableNext) {
        document.getElementById("page-number").innerText = pageText;
        document.getElementById("prev-btn").disabled = disablePrev;
        document.getElementById("next-btn").disabled = disableNext;
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

    // Search functionality with automatic filtering
    const searchInput = document.getElementById("search-input");
    const startDateInput = document.getElementById("start-date");
    const endDateInput = document.getElementById("end-date");

    searchInput.addEventListener("input", filterTable);
    startDateInput.addEventListener("change", filterTable);
    endDateInput.addEventListener("change", filterTable);

    function filterTable() {
        const query = searchInput.value.toLowerCase();
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);

        rows = Array.from(tableBody.getElementsByTagName("tr"));
        filteredRows = rows.filter(row => {
            const rowText = row.textContent.toLowerCase();
            const lastUpdatedCell = row.querySelector("td:nth-child(8)"); // Assuming "Last Updated" is the 8th column
            const lastUpdatedDate = lastUpdatedCell ? new Date(lastUpdatedCell.textContent) : null;

            const matchesQuery = rowText.includes(query);
            const matchesDateRange = (!startDateInput.value || lastUpdatedDate >= startDate) &&
                                     (!endDateInput.value || lastUpdatedDate <= endDate);

            return matchesQuery && matchesDateRange;
        });

        resetPagination();
    }

    function resetSearch() {
        document.getElementById("search-input").value = "";
        rows = Array.from(tableBody.getElementsByTagName("tr"));
        filteredRows = [...rows];
        resetPagination();
    }

    function resetPagination() {
        currentPage = 1;
        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        showPage(currentPage);
    }

    // Populate table with items data
    function populateTable(items) {
        tableBody.innerHTML = items.length
            ? items.map(item => `
                <tr>
                    <td>${item.item_name}</td>
                    <td>${item.description}</td>
                    <td>${item.quantity}</td>
                    <td>${item.unit}</td>
                    <td>${item.status}</td>
                    <td>${item.last_updated}</td>
                    <td>${item.model_no}</td>
                    <td>${item.item_category}</td>
                    <td>${item.item_location}</td>
                </tr>
            `).join("")
            : "<tr><td colspan='12'>No records found.</td></tr>";
    }

    // Initialize table and pagination
    populateTable(itemsData);
    resetPagination();

    // Attach functions to window for button clicks
    window.nextPage = nextPage;
    window.prevPage = prevPage;
    window.searchTable = filterTable;
    window.resetSearch = resetSearch;

    // Create button functionality
    const createButton = document.getElementById("create-item-btn");
    createButton.addEventListener("click", function () {
        const itemNameInput = document.getElementById("item-name");
        const descriptionInput = document.getElementById("description");
        const quantityInput = document.getElementById("quantity");
        const unitInput = document.getElementById("unit");
        const statusInput = document.getElementById("status");
        const modelNoInput = document.getElementById("model-no");
        const itemCategoryInput = document.getElementById("item-category");
        const itemLocationInput = document.getElementById("item-location");

        // Validate inputs
        if (!itemNameInput.value || !descriptionInput.value || !quantityInput.value || !unitInput.value || !statusInput.value || !modelNoInput.value || !itemCategoryInput.value || !itemLocationInput.value) {
            alert("Please fill in all fields.");
            return;
        }

        const newItem = {
            item_name: itemNameInput.value,
            description: descriptionInput.value,
            quantity: quantityInput.value,
            unit: unitInput.value,
            status: statusInput.value,
            last_updated: new Date().toLocaleDateString(),
            model_no: modelNoInput.value,
            item_category: itemCategoryInput.value,
            item_location: itemLocationInput.value,
        };

        // Add the new item to the table
        const newRow = createTableRow(newItem);
        tableBody.appendChild(newRow);
        rows.push(newRow);
        filteredRows = [...rows];
        resetPagination();

        // Clear input fields
        itemNameInput.value = "";
        descriptionInput.value = "";
        quantityInput.value = "";
        unitInput.value = "";
        statusInput.value = "";
        modelNoInput.value = "";
        itemCategoryInput.value = "";
        itemLocationInput.value = "";

        // Close modal if applicable
        const createModal = document.getElementById("create-item-btn");
        if (createModal) {
            createModal.style.display = "none";
        }
    });

    function createTableRow(item) {
        const row = document.createElement("tr");
        row.innerHTML = `
            <td>${item.item_name}</td>
            <td>${item.description}</td>
            <td>${item.quantity}</td>
            <td>${item.unit}</td>
            <td>${item.status}</td>
            <td>${item.last_updated}</td>
            <td>${item.model_no}</td>
            <td>${item.item_category}</td>
            <td>${item.item_location}</td>
        `;
        return row;
    }
});



