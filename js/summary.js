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

    // Attach input event listener for automatic search
    const searchInput = document.getElementById("search-input");
    searchInput.addEventListener("input", searchTable);

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