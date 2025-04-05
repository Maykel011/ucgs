document.addEventListener("DOMContentLoaded", function () {
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

        arrow.addEventListener("click", function (event) {
            event.stopPropagation();
            parent.classList.toggle("active");
            savedDropdownState[dropdownText] = parent.classList.contains("active");
            localStorage.setItem("dropdownState", JSON.stringify(savedDropdownState));
        });
    });

    // ====================
    // PROFILE DROPDOWN
    // ====================
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");

    userIcon.addEventListener("click", function (event) {
        event.stopPropagation();
        userDropdown.classList.toggle("show");
    });

    document.addEventListener("click", function (event) {
        if (!userIcon.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });

    // ====================
    // MODAL FUNCTIONALITY
    // ====================
    const modal = document.getElementById("rejectModal");
    const closeModal = document.querySelector(".close");
    const cancelBtn = document.getElementById("cancelReject");
    const confirmReject = document.getElementById("confirmReject");
    const rejectionReason = document.getElementById("rejectionReason");
    const errorMessage = document.getElementById("error-message");
    let currentRequestId = null;

    // Modal event listeners
    closeModal.onclick = cancelBtn.onclick = function() {
        modal.style.display = "none";
        rejectionReason.value = "";
        errorMessage.textContent = "";
    };

    window.addEventListener("click", function(event) {
        if (event.target === modal) {
            modal.style.display = "none";
            rejectionReason.value = "";
            errorMessage.textContent = "";
        }
    });

    // ====================
    // TABLE FUNCTIONALITY
    // ====================
    const rowsPerPage = 7;
    let currentPage = 1;x
    const tableBody = document.querySelector(".item-table tbody");
    let rows = Array.from(tableBody.querySelectorAll("tr:not(.no-results)"));
    let filteredRows = [...rows];
    let totalPages = Math.ceil(filteredRows.length / rowsPerPage);

    // Initialize status attributes for existing rows
    rows.forEach(row => {
        const statusCell = row.querySelector(".status-cell");
        if (statusCell) {
            row.setAttribute("data-status", statusCell.textContent.trim());
        }
    });

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
            noResultsRow.innerHTML = "<td colspan='11'>No matching requests found</td>";
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

    function filterTable() {
        const searchQuery = document.getElementById("search-box").value.toLowerCase();
        const startDate = document.getElementById("start-date").value;
        const endDate = document.getElementById("end-date").value;

        filteredRows = rows.filter(row => {
            const rowText = row.innerText.toLowerCase();
            const requestDate = row.cells[9].textContent; // Request date cell
            const rowStatus = row.getAttribute("data-status");

            // Check search query
            const matchesSearch = searchQuery === "" || rowText.includes(searchQuery);
            
            // Check date range
            let matchesDate = true;
            if (startDate && endDate) {
                matchesDate = requestDate >= startDate && requestDate <= endDate;
            }
            
            return matchesSearch && matchesDate;
        });

        totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        currentPage = 1;
        showPage(currentPage);
    }

    // ====================
    // AUTO REFRESH FUNCTIONALITY
    // ====================
    let refreshInterval = 30000; // 30 seconds
    let refreshTimer;
    
    function startAutoRefresh() {
        refreshTimer = setInterval(refreshTable, refreshInterval);
    }
    
    function stopAutoRefresh() {
        clearInterval(refreshTimer);
    }
    
    async function refreshTable() {
        try {
            const response = await fetch("ItemBorrowed.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: "action=refresh"
            });
            
            const data = await response.json();
            
            if (data.rows && data.rows.length > 0) {
                tableBody.innerHTML = "";
                
                data.rows.forEach(row => {
                    const tr = document.createElement("tr");
                    tr.setAttribute("data-request-id", row.request_id);
                    
                    // Create all table cells
                    const cells = [
                        row.username,
                        row.item_name,
                        row.item_category,
                        row.date_needed,
                        row.return_date,
                        row.quantity,
                        row.purpose,
                        row.notes,
                        "", // Status cell will be handled separately
                        row.request_date,
                        ""  // Action cell will be handled separately
                    ];
                    
                    cells.forEach((cellContent, index) => {
                        const td = document.createElement("td");
                        if (index === 8) { // Status cell
                            td.className = "status-cell";
                            if (row.status === "Approved") {
                                td.innerHTML = `Approved<br>${row.processed_at}`;
                                td.style.color = "green";
                                td.style.whiteSpace = "pre-line";
                                tr.setAttribute("data-status", "Approved");
                            } else if (row.status === "Rejected") {
                                td.innerHTML = `Rejected<br>${row.processed_at}`;
                                td.style.color = "red";
                                td.style.whiteSpace = "pre-line";
                                tr.setAttribute("data-status", "Rejected");
                            } else {
                                td.textContent = row.status;
                                tr.setAttribute("data-status", row.status);
                            }
                        } else if (index === 10) { // Action cell
                            td.className = "action-cell";
                            if (row.status === "Pending") {
                                const approveBtn = document.createElement("button");
                                approveBtn.className = "approve-btn";
                                approveBtn.setAttribute("data-request-id", row.request_id);
                                approveBtn.textContent = "Approve";
                                
                                const rejectBtn = document.createElement("button");
                                rejectBtn.className = "reject-btn";
                                rejectBtn.setAttribute("data-request-id", row.request_id);
                                rejectBtn.textContent = "Reject";
                                
                                td.appendChild(approveBtn);
                                td.appendChild(rejectBtn);
                                
                                // Add event listeners to new buttons
                                approveBtn.addEventListener("click", handleApprove);
                                rejectBtn.addEventListener("click", handleReject);
                            } else {
                                const span = document.createElement("span");
                                span.className = "processed-label";
                                span.textContent = "Processed";
                                td.appendChild(span);
                            }
                        } else {
                            td.textContent = cellContent;
                        }
                        tr.appendChild(td);
                    });
                    
                    tableBody.appendChild(tr);
                });
                
                // Reinitialize rows and filteredRows
                rows = Array.from(tableBody.querySelectorAll("tr:not(.no-results)"));
                filteredRows = [...rows];
                totalPages = Math.ceil(filteredRows.length / rowsPerPage);
                showPage(currentPage);
            }
        } catch (error) {
            console.error("Error refreshing table:", error);
        }
    }

    // ====================
    // REQUEST HANDLING
    // ====================
    async function handleApprove() {
        const requestId = this.getAttribute("data-request-id");
        
        if (confirm("Are you sure you want to approve this request?")) {
            try {
                const response = await fetch("ItemBorrowed.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: `action=approve&request_id=${requestId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Refresh the table to get updated data from server
                    await refreshTable();
                    showNotification("Request approved successfully", "success");
                } else {
                    showNotification("Error approving request", "error");
                }
            } catch (error) {
                console.error("Error:", error);
                showNotification("An error occurred while approving the request", "error");
            }
        }
    }
    
    async function handleReject() {
        const requestId = this.getAttribute("data-request-id");
        currentRequestId = requestId;
        modal.style.display = "flex";
        rejectionReason.focus();
    }
    
    // Update confirm rejection handler
    confirmReject.addEventListener("click", async function() {
        const reason = rejectionReason.value.trim();
        
        if (!reason) {
            errorMessage.textContent = "Please provide a reason for rejection.";
            return;
        }
        
        try {
            const response = await fetch("ItemBorrowed.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `action=reject&request_id=${currentRequestId}&reason=${encodeURIComponent(reason)}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Refresh the table to get updated data from server
                await refreshTable();
                
                // Close modal and reset
                modal.style.display = "none";
                rejectionReason.value = "";
                errorMessage.textContent = "";
                
                showNotification("Request rejected successfully", "success");
            } else {
                showNotification("Error rejecting request", "error");
            }
        } catch (error) {
            console.error("Error:", error);
            showNotification("An error occurred while rejecting the request", "error");
        }
    });

    // ====================
    // EVENT LISTENERS
    // ====================
    document.getElementById("search-box").addEventListener("input", debounce(filterTable, 300));
    document.getElementById("start-date").addEventListener("change", filterTable);
    document.getElementById("end-date").addEventListener("change", filterTable);
    document.getElementById("prev-btn").addEventListener("click", prevPage);
    document.getElementById("next-btn").addEventListener("click", nextPage);

    // Add event listeners to initial approve/reject buttons
    document.querySelectorAll(".approve-btn").forEach(button => {
        button.addEventListener("click", handleApprove);
    });
    
    document.querySelectorAll(".reject-btn").forEach(button => {
        button.addEventListener("click", handleReject);
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

    function showNotification(message, type) {
        const notification = document.createElement("div");
        notification.className = `notification ${type}`;
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add("fade-out");
            setTimeout(() => notification.remove(), 500);
        }, 3000);
    }

    // ====================
    // INITIALIZATION
    // ====================
    showPage(currentPage);
    startAutoRefresh();
    
    // Stop auto-refresh when page is unloaded
    window.addEventListener("beforeunload", stopAutoRefresh);
});