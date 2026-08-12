<div id="verification-modal"
  class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
    <div class="bg-gray-50 dark:bg-gray-950 px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
      <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
        <i data-lucide="badge-check" class="w-5 h-5 mr-2 text-brand-500"></i>
        Verified Pet Parent
      </h2>
      <button onclick="closeVerificationModal()"
        class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 p-1.5 rounded-full transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6 overflow-y-auto">
      <div id="verification-status-banner" class="hidden mb-4 p-3 rounded-xl text-sm"></div>

      <div id="verification-form-wrap">
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Verified pet parents get a badge on their profile. Confirm you're this pet's real owner with a photo of you both and one proof of ownership.</p>
        <div id="verification-modal-error" class="hidden mb-4 bg-red-50 border-l-4 border-red-400 p-3 rounded text-sm text-red-700"></div>

        <form id="verification-form" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Your name</label>
            <input type="text" id="vf-parent-name" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Proof of ownership</label>
            <select id="vf-proof-type" onchange="onVerificationProofTypeChange()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
              <option value="microchip">Microchip number</option>
              <option value="vet_record">Vet record</option>
              <option value="adoption_papers">Adoption / shelter papers</option>
              <option value="photo_only">Photo only (no document)</option>
            </select>
          </div>

          <div id="vf-microchip-wrap">
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Microchip number</label>
            <input type="text" id="vf-microchip-number" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
          </div>

          <div id="vf-document-wrap" class="hidden">
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Photo of the document</label>
            <input type="file" id="vf-document-input" accept="image/jpeg,image/png,image/webp" onchange="handleVerificationDocumentUpload(this)"
              class="w-full text-sm text-gray-600 dark:text-gray-300">
            <p id="vf-document-status" class="text-xs text-gray-400 mt-1"></p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Photo of your pet</label>
              <input type="file" id="vf-pet-photo-input" accept="image/jpeg,image/png,image/webp" onchange="handleVerificationPetPhotoUpload(this)"
                class="w-full text-sm text-gray-600 dark:text-gray-300">
              <p id="vf-pet-photo-status" class="text-xs text-gray-400 mt-1"></p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Photo of you together</label>
              <input type="file" id="vf-owner-photo-input" accept="image/jpeg,image/png,image/webp" onchange="handleVerificationOwnerPhotoUpload(this)"
                class="w-full text-sm text-gray-600 dark:text-gray-300">
              <p id="vf-owner-photo-status" class="text-xs text-gray-400 mt-1"></p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">City</label>
            <input type="text" id="vf-current-city" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Anything else? <span class="font-normal text-gray-400">(optional)</span></label>
            <textarea id="vf-reason" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm resize-none"></textarea>
          </div>

          <button type="button" id="submit-verification-btn" onclick="submitVerificationRequest()"
            class="w-full px-4 py-2.5 rounded-lg text-sm font-bold text-white bg-brand-500 hover:bg-brand-600 transition-colors">
            Submit for review
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
