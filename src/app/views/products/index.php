<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Products</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-100">
  <div class="max-w-5xl mx-auto p-6">
    <div class="bg-white shadow-md rounded-lg p-6 space-y-4">
      <header class="flex items-end justify-between gap-4">
        <div>
          <h1 class="text-2xl font-semibold text-slate-800">Products</h1>
          <p class="text-xs text-slate-400">Page <?= (int)$page ?> · <?= (int)$perPage ?> per page</p>
        </div>
        <a href="/upload" class="text-sm text-indigo-700 hover:underline">Back to upload</a>
      </header>

      <?php if (empty($products)): ?>
        <div class="text-sm text-slate-600 bg-slate-50 border border-slate-200 rounded-md p-4">
          No products found for this page.
        </div>
      <?php else: ?>
        <?php $columns = array_keys($products[0]); ?>

        <div class="overflow-auto border border-slate-200 rounded-lg">
          <table class="min-w-full text-sm">
            <thead class="bg-indigo-800 text-white">
              <tr>
                <?php foreach ($columns as $col): ?>
                  <th class="text-left font-semibold px-4 py-3 whitespace-nowrap">
                    <?= htmlspecialchars((string)$col) ?>
                  </th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <?php foreach ($products as $row): ?>
                <tr class="hover:bg-slate-50">
                  <?php foreach ($columns as $col): ?>
                    <td class="px-4 py-3 whitespace-nowrap text-slate-700">
                      <?= htmlspecialchars((string)($row[$col] ?? '')) ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <nav class="flex items-center justify-between pt-2">
        <div class="flex items-center gap-2">
          <?php if ($hasPrev): ?>
            <a
              href="/products?page=<?= (int)($page - 1) ?>"
              class="px-3 py-2 text-sm rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-slate-700"
            >Previous</a>
          <?php else: ?>
            <span class="px-3 py-2 text-sm rounded-md border border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed">Previous</span>
          <?php endif; ?>

          <span class="px-3 py-2 text-sm rounded-md bg-slate-100 text-slate-700">
            Page <?= (int)$page ?>
          </span>

          <?php if ($hasNext): ?>
            <a
              href="/products?page=<?= (int)($page + 1) ?>"
              class="px-3 py-2 text-sm rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-slate-700"
            >Next</a>
          <?php else: ?>
            <span class="px-3 py-2 text-sm rounded-md border border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed">Next</span>
          <?php endif; ?>
        </div>
      </nav>
    </div>
  </div>
</body>
</html>
