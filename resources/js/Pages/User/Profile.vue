<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import UserDashboardLayout from '@/Layouts/UserDashboardLayout.vue'

defineOptions({ layout: UserDashboardLayout })

const page = usePage()
const user = computed(() => page.props.auth?.user as any)

const profileForm = useForm({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
})

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const avatarForm = useForm({ avatar: null as File | null })
const avatarPreview = ref<string | null>(user.value?.avatar ? '/storage/' + user.value.avatar : null)

const showCurrentPw = ref(false)
const showNewPw = ref(false)
const showConfirmPw = ref(false)

const onAvatarChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    avatarForm.avatar = file
    avatarPreview.value = URL.createObjectURL(file)
}

const uploadAvatar = () => {
    avatarForm.put(route('user.dashboard.avatar.update'), {
        onSuccess: () => {
            avatarForm.reset()
        },
    })
}
</script>

<template>
    <Head :title="$t('Profile')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('Profile') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $t('Manage your profile information.') }}</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Left column: Avatar + Profile info -->
            <div class="space-y-6">
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 p-6">
                    <h2 class="font-semibold text-gray-900 dark:text-white mb-4">{{ $t('Avatar') }}</h2>
                    <div class="flex items-center gap-5">
                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <img v-if="avatarPreview" :src="avatarPreview" class="h-full w-full object-cover" alt="avatar" />
                            <div v-else class="flex h-full w-full items-center justify-center text-3xl font-bold text-gray-400">{{ (user?.name || '?')[0]?.toUpperCase() }}</div>
                        </div>
                        <form @submit.prevent="uploadAvatar" class="flex-1">
                            <input id="avatar-upload" type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                            <label for="avatar-upload" class="inline-block cursor-pointer rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 transition">
                                {{ $t('Choose photo') }}
                            </label>
                            <button v-if="avatarForm.avatar" type="submit" :disabled="avatarForm.processing" class="ml-3 rounded-lg bg-[#1F75FE] px-4 py-2 text-sm font-semibold text-white hover:bg-[#1a65e0] transition disabled:opacity-60">
                                {{ avatarForm.processing ? $t('Uploading...') : $t('Upload') }}
                            </button>
                            <p v-if="avatarForm.errors.avatar" class="mt-1.5 text-xs text-red-500">{{ avatarForm.errors.avatar }}</p>
                        </form>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 p-6">
                    <h2 class="font-semibold text-gray-900 dark:text-white mb-5">{{ $t('Profile information') }}</h2>
                    <form @submit.prevent="profileForm.put(route('user.dashboard.profile.update'))" class="space-y-4">
                        <div>
                            <label for="profile-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Name') }}</label>
                            <input id="profile-name" v-model="profileForm.name" type="text" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            <p v-if="profileForm.errors.name" class="mt-1 text-xs text-red-500">{{ profileForm.errors.name }}</p>
                        </div>
                        <div>
                            <label for="profile-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Email') }}</label>
                            <input id="profile-email" v-model="profileForm.email" type="email" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            <p v-if="profileForm.errors.email" class="mt-1 text-xs text-red-500">{{ profileForm.errors.email }}</p>
                        </div>
                        <button type="submit" :disabled="profileForm.processing" class="rounded-xl bg-[#1F75FE] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#1a65e0] transition disabled:opacity-60">
                            {{ profileForm.processing ? $t('Saving...') : $t('Save changes') }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right column: Password -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 p-6">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-5">{{ $t('Change password') }}</h2>
                <form @submit.prevent="passwordForm.put(route('user.dashboard.password.update'))" class="space-y-4">
                    <div>
                        <label for="current-pw" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Current password') }}</label>
                        <div class="relative">
                            <input id="current-pw" v-model="passwordForm.current_password" :type="showCurrentPw ? 'text' : 'password'" required autocomplete="current-password" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="showCurrentPw = !showCurrentPw">
                                <i :class="showCurrentPw ? 'ti ti-eye-off' : 'ti ti-eye'"></i>
                            </button>
                        </div>
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-red-500">{{ passwordForm.errors.current_password }}</p>
                    </div>
                    <div>
                        <label for="new-pw" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('New password') }}</label>
                        <div class="relative">
                            <input id="new-pw" v-model="passwordForm.password" :type="showNewPw ? 'text' : 'password'" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="showNewPw = !showNewPw">
                                <i :class="showNewPw ? 'ti ti-eye-off' : 'ti ti-eye'"></i>
                            </button>
                        </div>
                        <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-red-500">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div>
                        <label for="confirm-pw" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">{{ $t('Confirm new password') }}</label>
                        <div class="relative">
                            <input id="confirm-pw" v-model="passwordForm.password_confirmation" :type="showConfirmPw ? 'text' : 'password'" required class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 pr-10 text-sm text-gray-900 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/10 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white" />
                            <button type="button" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" @click="showConfirmPw = !showConfirmPw">
                                <i :class="showConfirmPw ? 'ti ti-eye-off' : 'ti ti-eye'"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" :disabled="passwordForm.processing" class="rounded-xl bg-[#1F75FE] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#1a65e0] transition disabled:opacity-60">
                        {{ passwordForm.processing ? $t('Changing...') : $t('Change password') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
