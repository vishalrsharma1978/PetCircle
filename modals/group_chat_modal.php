<div id="group-chat-modal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col h-[80vh]">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between flex-shrink-0">
      <div class="min-w-0">
        <h3 id="group-chat-title" class="font-bold text-gray-900 dark:text-white truncate">Group</h3>
        <p id="group-chat-subtitle" class="text-xs text-gray-500 dark:text-gray-400"></p>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        <button onclick="startZoomCall({callType:'voice',targetType:'group',groupId:currentGroupId}, this)" class="p-1.5 rounded-full text-brand-500 hover:bg-brand-100 dark:hover:bg-brand-900/40" title="Voice call"><i data-lucide="phone" class="w-4 h-4"></i></button>
        <button onclick="startZoomCall({callType:'video',targetType:'group',groupId:currentGroupId}, this)" class="p-1.5 rounded-full text-brand-500 hover:bg-brand-100 dark:hover:bg-brand-900/40" title="Video call"><i data-lucide="video" class="w-4 h-4"></i></button>
        <button id="group-chat-leave-btn" onclick="leaveCurrentGroup()" class="text-xs font-semibold text-red-500 hover:text-red-600">Leave</button>
        <button onclick="closeGroupChatModal()" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
    </div>
    <!-- Chat and Posts are separate streams, not one interleaved timeline:
         posts go to members' feeds, messages stay here. They also live in
         separate containers so the 3s message poll's innerHTML replace (and
         its scroll-to-bottom) can never touch the posts pane. -->
    <div class="px-3 pt-3 flex-shrink-0">
      <div class="flex gap-1 rounded-xl bg-gray-100 dark:bg-gray-800 p-1">
        <button type="button" id="group-tab-chat-btn" onclick="switchGroupModalTab('chat')" class="flex-1 py-1.5 rounded-lg text-xs font-bold transition-colors">Chat</button>
        <button type="button" id="group-tab-posts-btn" onclick="switchGroupModalTab('posts')" class="flex-1 py-1.5 rounded-lg text-xs font-bold transition-colors">Posts</button>
      </div>
    </div>

    <div id="group-chat-messages" class="flex-1 overflow-y-auto p-4 space-y-2"></div>
    <div id="group-posts-pane" class="hidden flex-1 overflow-y-auto p-4 space-y-4"></div>

    <div id="group-chat-reply-strip" class="hidden flex-shrink-0"></div>
    <div id="group-chat-composer" class="p-3 border-t border-gray-100 dark:border-gray-800 flex items-center gap-2 flex-shrink-0">
      <input type="text" id="group-chat-input" placeholder="Message the group…" onkeydown="if(event.key==='Enter'){submitGroupMessage();}"
        class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-full text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      <button onclick="submitGroupMessage()" class="w-9 h-9 rounded-full bg-brand-500 hover:bg-brand-600 text-white flex items-center justify-center flex-shrink-0">
        <i data-lucide="send" class="w-4 h-4"></i>
      </button>
    </div>

    <div id="group-posts-composer" class="hidden p-3 border-t border-gray-100 dark:border-gray-800 flex-shrink-0">
      <div id="group-post-media-preview" class="hidden mb-2">
        <img id="group-post-media-img" src="" alt="" class="max-h-32 rounded-xl object-cover">
        <button type="button" onclick="clearGroupPostMedia()" class="text-xs text-red-500 font-semibold mt-1">Remove photo</button>
      </div>
      <div class="flex items-center gap-2">
        <label class="p-2 rounded-full text-gray-400 hover:text-brand-500 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer flex-shrink-0" title="Add a photo">
          <i data-lucide="image" class="w-4 h-4"></i>
          <input type="file" id="group-post-media-input" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="handleGroupPostMediaUpload(this)">
        </label>
        <input type="text" id="group-post-input" placeholder="Share something with the group…" onkeydown="if(event.key==='Enter'){submitGroupPost();}"
          class="flex-1 px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-full text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        <button id="group-post-submit-btn" onclick="submitGroupPost()" class="px-4 h-9 rounded-full bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold flex-shrink-0">Post</button>
      </div>
    </div>
  </div>
</div>
