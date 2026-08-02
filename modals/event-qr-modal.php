<div id="event-qr-modal" class="fixed inset-0 z-[9000] items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-xs text-center p-6 relative">
      <button onclick="closeEventQRModal()"
        class="no-faith-hover absolute top-3 right-3 p-1 rounded-lg text-gray-400 hover:text-gray-600">
        <i data-lucide="x" class="w-4 h-4"></i>
      </button>
      <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-3">Event Check-in QR</p>
      <h3 class="text-base font-bold text-gray-800 dark:text-white mb-4" id="qr-event-name">Event Ticket</h3>
      <div id="qr-code-container" class="flex justify-center mb-4"></div>
      <p class="text-xs text-gray-500 dark:text-gray-400">Show this QR code at the venue to check in.</p>
    </div>
  </div>