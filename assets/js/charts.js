document.addEventListener("DOMContentLoaded", function () {
    if (typeof window.dashboardData === "undefined") {
        return;
    }

    const {
        income,
        expense,
        categoryLabels,
        categoryValues,
        trendLabels,
        trendIncomeValues,
        trendExpenseValues,
    } = window.dashboardData;

    const pieCanvas = document.getElementById("pieChart");
    if (pieCanvas && categoryLabels.length > 0) {
        new Chart(pieCanvas, {
            type: "pie",
            data: {
                labels: categoryLabels,
                datasets: [
                    {
                        data: categoryValues,
                        backgroundColor: [
                            "#0d6efd",
                            "#dc3545",
                            "#198754",
                            "#fd7e14",
                            "#6f42c1",
                            "#20c997",
                            "#ffc107",
                            "#6c757d",
                        ],
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: "bottom",
                    },
                },
            },
        });
    }

    const barCanvas = document.getElementById("barChart");
    if (barCanvas) {
        new Chart(barCanvas, {
            type: "bar",
            data: {
                labels: ["Income", "Expense"],
                datasets: [
                    {
                        label: "Amount",
                        data: [income, expense],
                        backgroundColor: ["#198754", "#dc3545"],
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false,
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                    },
                },
            },
        });
    }

    const lineCanvas = document.getElementById("lineChart");
    if (lineCanvas) {
        new Chart(lineCanvas, {
            type: "line",
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: "Income",
                        data: trendIncomeValues,
                        borderColor: "#198754",
                        backgroundColor: "rgba(25, 135, 84, 0.18)",
                        fill: false,
                        tension: 0.3,
                    },
                    {
                        label: "Expense",
                        data: trendExpenseValues,
                        borderColor: "#dc3545",
                        backgroundColor: "rgba(220, 53, 69, 0.18)",
                        fill: false,
                        tension: 0.3,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: "bottom",
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                    },
                },
            },
        });
    }
});
