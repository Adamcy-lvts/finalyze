<!-- /resources/js/pages/auth/InviteOnly.vue -->
<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import TextLink from '@/components/TextLink.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import AuthBase from '@/layouts/AuthLayout.vue'
import { Form, Head } from '@inertiajs/vue3'
import { LoaderCircle } from 'lucide-vue-next'
import { ref, watch } from 'vue'

const props = defineProps<{
  prefillCode?: string | null
  inviteOnlyEnabled?: boolean
}>()

const code = ref(props.prefillCode ?? '')

watch(
  () => props.prefillCode,
  (next) => {
    if (typeof next === 'string' && next.length > 0 && code.value.trim().length === 0) {
      code.value = next
    }
  },
)
</script>

<template>
  <AuthBase
    title="Invite only"
    description="Registration is currently limited. Enter your invite code to continue."
  >
    <Head title="Invite Only" />

    <Form
      method="post"
      :action="route('invite.verify')"
      v-slot="{ errors, processing }"
      class="flex flex-col gap-6"
    >
      <div class="grid gap-6">
        <div class="grid gap-2">
          <Label for="code">Invite code</Label>
          <Input
            id="code"
            name="code"
            autocomplete="one-time-code"
            placeholder="e.g. 4F8K9Q2JH7"
            required
            autofocus
            :tabindex="1"
            v-model="code"
          />
          <InputError :message="errors.code" />
        </div>

        <Button type="submit" class="mt-2 w-full" tabindex="2" :disabled="processing">
          <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
          Continue to registration
        </Button>

        <div
          v-if="inviteOnlyEnabled === false"
          class="rounded-md border border-border bg-muted/20 px-3 py-2 text-sm text-muted-foreground"
        >
          Invite-only mode is currently disabled; you can also <TextLink :href="route('register')">register directly</TextLink>.
        </div>
      </div>

      <div class="text-center text-sm text-muted-foreground">
        Already have an account?
        <TextLink :href="route('login')" class="underline underline-offset-4" :tabindex="3">Log in</TextLink>
      </div>
    </Form>
  </AuthBase>
</template>

