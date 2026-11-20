<div class="flex min-w-0 flex-col justify-between items-center rounded-xl border-2 overflow-hidden p-5 h-full" style="border-color: <?= htmlspecialchars($borderColor, ENT_QUOTES, 'UTF-8') ?>">
    <div class="w-11 block max-w-full h-auto"><?= $iconHtml ?></div>
    <div class="flex-grow"></div>
    <ul class="flex flex-col items-center justify-center bg-[#001] overflow-hidden w-full">
        <p class="text-lg font-semibold"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></p>
        <?php foreach ($card as $key => $value): if (!in_array($key, $visibleFields, true)) continue; ?>
            <li class="grid grid-cols-2 gap-1 w-full">
                <span class="text-gray-300 text-right break-all text-xs"><?= htmlspecialchars($definer->getLabels($key), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="font-medium break-all text-xs">= <?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="text-right text-gray-400 text-sm mt-4"></div>
</div>
