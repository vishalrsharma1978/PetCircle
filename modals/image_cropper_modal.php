<div id="image-cropper-modal"
    class="fixed inset-0 hidden items-center justify-center bg-gray-900/80 backdrop-blur-sm p-4"
    style="z-index: 99999;">
    <div
      class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden animate-[fadeSlideIn_0.3s_ease]">
      <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
        <h3 class="font-bold text-gray-800 dark:text-gray-100 font-serif">Crop Image</h3>
        <button onclick="closeCropperModal()"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-4 bg-gray-100 dark:bg-gray-950 flex items-center justify-center relative" style="max-height: 60vh;">
        <div class="w-full h-full min-h-[300px]">
          <img id="cropper-image" src="" alt="Image for cropping" class="max-w-full hidden">
        </div>
      </div>
      <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-3 bg-white dark:bg-gray-900">
        <button onclick="closeCropperModal()"
          class="px-4 py-2 rounded-xl text-sm font-bold border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">Cancel</button>
        <button onclick="applyCroppedImage()" id="apply-crop-btn"
          class="px-5 py-2 rounded-xl text-sm font-bold bg-brand-500 text-white hover:bg-brand-600 transition-colors flex items-center gap-2 shadow-sm">
          <i data-lucide="crop" class="w-4 h-4"></i> Save Crop
        </button>
      </div>
    </div>
  </div>