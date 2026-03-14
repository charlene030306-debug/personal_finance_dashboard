<?php
$current_path = str_replace("\\", "/", $_SERVER["PHP_SELF"] ?? "");
$is_auth_page = str_contains($current_path, "/auth/");
?>
<?php if ($is_auth_page): ?>
    </div>
<?php else: ?>
            </div>
        </main>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
