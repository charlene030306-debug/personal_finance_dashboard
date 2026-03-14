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
        budgetTotal,
        budgetSpent,
        budgetRemaining,
    } = window.dashboardData;

    Chart.defaults.font.family = "Manrope, system-ui, -apple-system, sans-serif";
    Chart.defaults.color = "#475569";

    const commonAnimation = {
        duration: 900,
        easing: "easeOutQuart",
    };

    const pieCanvas = document.getElementById("pieChart");
    if (pieCanvas && categoryLabels.length > 0) {
        new Chart(pieCanvas, {
            type: "doughnut",
            data: {
                labels: categoryLabels,
                datasets: [
                    {
                        data: categoryValues,
                        backgroundColor: [
                            "#3b82f6",
                            "#ef4444",
                            "#10b981",
                            "#f59e0b",
                            "#6366f1",
                            "#06b6d4",
                            "#8b5cf6",
                            "#14b8a6",
                        ],
                        borderWidth: 0,
                        hoverOffset: 8,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                cutout: "62%",
                animation: commonAnimation,
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            padding: 16,
                        },
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
                        backgroundColor: ["#12b76a", "#ef4444"],
                        borderRadius: 10,
                        maxBarThickness: 60,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                animation: commonAnimation,
                plugins: {
                    legend: {
                        display: false,
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: "rgba(148, 163, 184, 0.2)",
                        },
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
                        borderColor: "#16a34a",
                        backgroundColor: "rgba(22, 163, 74, 0.14)",
                        fill: true,
                        tension: 0.34,
                        pointRadius: 3.5,
                        pointHoverRadius: 5,
                    },
                    {
                        label: "Expense",
                        data: trendExpenseValues,
                        borderColor: "#dc2626",
                        backgroundColor: "rgba(220, 38, 38, 0.12)",
                        fill: true,
                        tension: 0.34,
                        pointRadius: 3.5,
                        pointHoverRadius: 5,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                animation: commonAnimation,
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            padding: 16,
                        },
                    },
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: "rgba(148, 163, 184, 0.2)",
                        },
                    },
                },
            },
        });
    }

    const budgetSummaryCanvas = document.getElementById("budgetSummaryChart");
    if (budgetSummaryCanvas) {
        new Chart(budgetSummaryCanvas, {
            type: "doughnut",
            data: {
                labels: ["Spent", "Remaining"],
                datasets: [
                    {
                        data: [budgetSpent, budgetRemaining],
                        backgroundColor: ["#ef4444", "#10b981"],
                        borderWidth: 0,
                        hoverOffset: 8,
                    },
                ],
            },
            options: {
                maintainAspectRatio: false,
                cutout: "65%",
                animation: commonAnimation,
                plugins: {
                    legend: {
                        position: "bottom",
                        labels: {
                            usePointStyle: true,
                            padding: 16,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return `${ctx.label}: Rs ${Number(ctx.raw || 0).toFixed(2)}`;
                            },
                        },
                    },
                },
            },
        });
    }
});
