<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle, User, Briefcase, GraduationCap, ArrowLeft, Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const selectedRole = ref<'admin' | 'accounting' | 'student' | null>(null);
const showPassword = ref(false);

const roleOptions = [
    { 
        value: 'admin', 
        label: 'Admin', 
        icon: User,
        color: 'bg-red-600 hover:bg-red-700',
        borderColor: 'border-red-600',
        description: 'System administrators and managers'
    },
    { 
        value: 'accounting', 
        label: 'Accounting', 
        icon: Briefcase,
        color: 'bg-blue-600 hover:bg-blue-700',
        borderColor: 'border-blue-600',
        description: 'Accounting staff and financial officers'
    },
    { 
        value: 'student', 
        label: 'Student', 
        icon: GraduationCap,
        color: 'bg-green-600 hover:bg-green-700',
        borderColor: 'border-green-600',
        description: 'Students and learners'
    },
] as const;

const selectRole = (role: 'admin' | 'accounting' | 'student') => {
    selectedRole.value = role;
};

const backToRoleSelection = () => {
    selectedRole.value = null;
    showPassword.value = false;
};

const getCurrentRole = () => {
    return roleOptions.find(r => r.value === selectedRole.value);
};

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};
</script>

<template>
    <AuthBase 
        :title="selectedRole ? `Log in as ${getCurrentRole()?.label}` : 'Welcome to CCDI Account Portal'" 
        :description="selectedRole ? 'Enter your credentials to continue' : 'Select your role to get started'"
    >
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <!-- Role Selection Screen -->
        <div v-if="!selectedRole" class="space-y-4">
            <div class="text-center mb-6">
                <p class="text-gray-600 text-sm">Choose your role to access the portal</p>
            </div>

            <div class="space-y-3">
                <button
                    v-for="role in roleOptions"
                    :key="role.value"
                    type="button"
                    @click="selectRole(role.value)"
                    :class="[
                        'w-full p-4 rounded-lg text-white font-medium transition-all',
                        'flex items-center gap-4 hover:scale-105 transform',
                        role.color
                    ]"
                >
                    <div class="p-3 bg-white/20 rounded-lg">
                        <component :is="role.icon" :size="24" />
                    </div>
                    <div class="flex-1 text-left">
                        <p class="font-semibold text-lg">{{ role.label }}</p>
                        <p class="text-sm text-white/80">{{ role.description }}</p>
                    </div>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <div class="text-center text-sm text-muted-foreground pt-4 border-t">
                Don't have an account?
                <TextLink :href="register()">Sign up as Student</TextLink>
            </div>
        </div>

        <!-- Login Form (appears after role selection) -->
        <div v-else class="space-y-6">
            <!-- Role Badge with Back Button -->
            <div class="flex items-center justify-between p-4 rounded-lg border-2" :class="getCurrentRole()?.borderColor">
                <div class="flex items-center gap-3">
                    <div :class="['p-2 rounded-lg text-white', getCurrentRole()?.color]">
                        <component :is="getCurrentRole()?.icon" :size="20" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Logging in as</p>
                        <p class="font-semibold text-gray-900">{{ getCurrentRole()?.label }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="backToRoleSelection"
                    class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                    title="Change role"
                >
                    <ArrowLeft :size="20" class="text-gray-600" />
                </button>
            </div>

            <Form
                v-bind="AuthenticatedSessionController.store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <!-- Hidden role field -->
                <input type="hidden" name="role" :value="selectedRole" />

                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="email@example.com"
                            :class="{ 'border-red-500': errors.email }"
                        />
                        <InputError :message="errors.email" />
                        <p v-if="errors.email" class="text-xs text-gray-600 mt-1">
                            💡 Tip: Make sure you're using the correct login portal for your role.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label for="password">Password</Label>
                            <TextLink v-if="canResetPassword" :href="request()" class="text-sm" :tabindex="5">
                                Forgot password?
                            </TextLink>
                        </div>
                        <div class="relative">
                            <Input
                                id="password"
                                :type="showPassword ? 'text' : 'password'"
                                name="password"
                                required
                                :tabindex="2"
                                autocomplete="current-password"
                                placeholder="Password"
                                class="pr-10"
                            />
                            <button
                                type="button"
                                @click="togglePasswordVisibility"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-sm"
                                :tabindex="-1"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            >
                                <Eye v-if="!showPassword" :size="18" class="transition-opacity" />
                                <EyeOff v-else :size="18" class="transition-opacity" />
                            </button>
                        </div>
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <Label for="remember" class="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" :tabindex="3" />
                            <span>Remember me</span>
                        </Label>
                    </div>

                    <Button 
                        type="submit" 
                        class="mt-4 w-full" 
                        :class="getCurrentRole()?.color"
                        :tabindex="4" 
                        :disabled="processing"
                    >
                        <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin mr-2" />
                        Log in
                    </Button>
                </div>

                <div class="text-center text-sm text-muted-foreground">
                    <button
                        type="button"
                        @click="backToRoleSelection"
                        class="text-blue-600 hover:underline"
                    >
                        ← Back to role selection
                    </button>
                </div>
            </Form>
        </div>
    </AuthBase>
</template>
