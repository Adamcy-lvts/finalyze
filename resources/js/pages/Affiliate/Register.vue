<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import TextLink from '@/components/TextLink.vue'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AuthBase from '@/layouts/AuthLayout.vue'
import { Form, Head } from '@inertiajs/vue3'
import { LoaderCircle, Eye, EyeOff, Sparkles } from 'lucide-vue-next'
import { computed, ref } from 'vue'

interface Props {
    registrationOpen: boolean
    affiliateEnabled: boolean
    hasInvite: boolean
}

const props = defineProps<Props>()

const showPassword = ref(false)
const showPasswordConfirmation = ref(false)

const canRegister = computed(() => props.affiliateEnabled && (props.registrationOpen || props.hasInvite))
</script>

<template>
    <AuthBase title="Join the Affiliate Program" description="Earn commission by sharing your referral link">
        <Head title="Affiliate Registration" />

        <Alert v-if="!affiliateEnabled" class="mb-6">
            <AlertDescription>
                The affiliate program is currently disabled. Please check back later.
            </AlertDescription>
        </Alert>

        <Alert v-else-if="!registrationOpen && !hasInvite" class="mb-6">
            <AlertDescription>
                Affiliate registration is currently closed. You will need an invite to join.
            </AlertDescription>
        </Alert>

        <Alert v-else class="mb-6 border-primary/20 bg-primary/5">
            <Sparkles class="h-4 w-4 text-primary" />
            <AlertDescription class="text-primary">
                You are creating an affiliate-only account. You can always request dual access later.
            </AlertDescription>
        </Alert>

        <Form
            method="post"
            :action="route('affiliate.register.store')"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" type="text" required autofocus autocomplete="name" name="name" placeholder="Full name" :disabled="!canRegister" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input id="email" type="email" required autocomplete="email" name="email" placeholder="email@example.com" :disabled="!canRegister" />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <div class="relative">
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            autocomplete="new-password"
                            name="password"
                            placeholder="Password"
                            class="pr-10"
                            :disabled="!canRegister"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 mr-2 flex items-center rounded-md px-2 text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @click="showPassword = !showPassword"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        >
                            <Eye v-if="!showPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <div class="relative">
                        <Input
                            id="password_confirmation"
                            :type="showPasswordConfirmation ? 'text' : 'password'"
                            required
                            autocomplete="new-password"
                            name="password_confirmation"
                            placeholder="Confirm password"
                            class="pr-10"
                            :disabled="!canRegister"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 mr-2 flex items-center rounded-md px-2 text-muted-foreground transition hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            @click="showPasswordConfirmation = !showPasswordConfirmation"
                            :aria-label="showPasswordConfirmation ? 'Hide password' : 'Show password'"
                        >
                            <Eye v-if="!showPasswordConfirmation" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" :disabled="processing || !canRegister">
                    <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
                    Create affiliate account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="underline underline-offset-4">Log in</TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
