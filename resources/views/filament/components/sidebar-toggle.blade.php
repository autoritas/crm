<div class="flex items-center px-4 py-2 border-b border-gray-200 dark:border-gray-700">
    <button
        x-data="{}"
        x-on:click="
            const sidebar = document.querySelector('.fi-sidebar');
            const isCollapsed = sidebar.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        "
        class="flex items-center gap-2 rounded-lg text-gray-400 transition duration-75 hover:text-gray-500 dark:hover:text-gray-400"
        title="Contraer/Expandir menu"
    >
        <x-heroicon-o-bars-3 class="w-5 h-5 shrink-0" />
    </button>
</div>
