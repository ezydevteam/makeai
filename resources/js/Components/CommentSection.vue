<script setup lang="ts">
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{
    comments: Array<any>,
    modelType: string,
    modelId: number
}>();

const page = usePage();
const authUser = ref(page.props.auth?.user);

const form = useForm({
    commentable_type: props.modelType,
    commentable_id: props.modelId,
    content: '',
    parent_id: null as number | null
});

const replyingTo = ref<any>(null);

const setReply = (comment: any) => {
    replyingTo.value = comment;
    form.parent_id = comment.id;
    // Scroll to form
    document.getElementById('comment-form')?.scrollIntoView({ behavior: 'smooth' });
};

const cancelReply = () => {
    replyingTo.value = null;
    form.parent_id = null;
};

const submit = () => {
    form.post(route('comments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('content');
            cancelReply();
        }
    });
};

const likeComment = (id: number) => {
    useForm({}).post(route('comments.like', id), { preserveScroll: true });
};
</script>

<template>
    <div class="space-y-8">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <h3 class="text-xl font-bold text-gray-900">{{ comments.length }} Comments</h3>
        </div>

        <!-- Comment Form -->
        <div id="comment-form" class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
            <div v-if="!authUser" class="text-center py-4">
                <p class="text-sm text-gray-500 mb-4">Please log in to join the discussion.</p>
                <Link :href="route('login')" class="text-sm font-bold text-primary-600 hover:underline">Log In</Link>
            </div>
            <form v-else @submit.prevent="submit" class="space-y-4">
                <div v-if="replyingTo" class="flex items-center justify-between bg-white px-4 py-2 rounded-lg border border-primary-100 mb-2">
                    <span class="text-xs text-gray-500">Replying to <span class="font-bold text-primary-600">@{{ replyingTo.user?.name || 'Guest' }}</span></span>
                    <button @click="cancelReply" class="text-gray-400 hover:text-gray-600"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </div>
                <textarea v-model="form.content" rows="3" placeholder="Share your thoughts..." class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm focus:border-primary-500 focus:outline-none transition-all resize-none" required></textarea>
                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-500 transition-all shadow-lg shadow-primary-500/20 disabled:opacity-50">
                        {{ form.processing ? 'Posting...' : 'Post Comment' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Comments List -->
        <div class="space-y-6">
            <div v-for="comment in comments.filter(c => !c.parent_id)" :key="comment.id" class="space-y-4">
                <!-- Parent Comment -->
                <div class="flex gap-4">
                    <img :src="comment.user?.avatar || `https://ui-avatars.com/api/?name=${comment.user?.name || 'G'}&background=random`" class="w-10 h-10 rounded-full border border-gray-100 shrink-0" />
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-bold text-gray-900 text-sm">{{ comment.user?.name || 'Guest' }}</span>
                            <span class="text-[10px] text-gray-400 font-medium">{{ new Date(comment.created_at).toLocaleDateString() }}</span>
                        </div>
                        <p class="text-sm text-gray-600 leading-relaxed mb-3">{{ comment.content }}</p>
                        <div class="flex items-center gap-4">
                            <button @click="likeComment(comment.id)" class="flex items-center gap-1 text-xs font-bold text-gray-400 hover:text-primary-600 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5" /></svg>
                                {{ comment.likes_count }}
                            </button>
                            <button v-if="authUser" @click="setReply(comment)" class="text-xs font-bold text-gray-400 hover:text-primary-600 transition-colors">Reply</button>
                        </div>
                    </div>
                </div>

                <!-- Replies -->
                <div v-if="comment.replies?.length" class="ml-14 space-y-4 border-l-2 border-gray-50 pl-6">
                    <div v-for="reply in comment.replies" :key="reply.id" class="flex gap-4">
                        <img :src="reply.user?.avatar || `https://ui-avatars.com/api/?name=${reply.user?.name || 'G'}&background=random`" class="w-8 h-8 rounded-full border border-gray-100 shrink-0" />
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-gray-900 text-xs">{{ reply.user?.name || 'Guest' }}</span>
                                <span class="text-[10px] text-gray-400 font-medium">{{ new Date(reply.created_at).toLocaleDateString() }}</span>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed">{{ reply.content }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
