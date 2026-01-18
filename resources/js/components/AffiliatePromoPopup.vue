<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Gift, Users } from 'lucide-vue-next'
import { toast } from 'vue-sonner'
import { route } from 'ziggy-js'

const page = usePage()
const affiliateProps = computed(() => page.props.affiliate)
const userAffiliate = computed(() => page.props.auth?.user?.affiliate)

const open = ref(Boolean(affiliateProps.value?.show_promo))

const canShow = computed(() => {
    return Boolean(affiliateProps.value?.enabled)
        && Boolean(affiliateProps.value?.show_promo)
        && !Boolean(userAffiliate.value?.is_affiliate)
})

const requestAffiliate = async () => {
    try {
        const response = await fetch(route('affiliate.request'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        })

        const data = await response.json()

        if (data.success) {
            toast.success('Affiliate request submitted!')
            open.value = false
        } else {
            toast.error(data.message || 'Could not submit request')
        }
    } catch (error) {
        toast.error('Could not submit request')
    }
}

const dismiss = async () => {
    try {
        await fetch(route('affiliate.promo.dismiss'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        })
    } catch (error) {
        // ignore
    } finally {
        open.value = false
    }
}
</script>

<template>
    <Dialog v-if="canShow" :open="open" @update:open="open = $event">
        <DialogContent class="max-w-lg">
            <DialogHeader class="space-y-2">
                <div class="flex items-center gap-2 text-primary">
                    <Gift class="h-5 w-5" />
                    <Badge variant="secondary" class="uppercase">New</Badge>
                </div>
                <DialogTitle class="text-xl">Become an Affiliate</DialogTitle>
                <p class="text-sm text-muted-foreground">
                    Earn commission on every purchase made by users you refer. Share your link and grow your earnings.
                </p>
            </DialogHeader>
            <div class="rounded-lg border border-border/60 bg-muted/40 p-4 text-sm">
                <div class="flex items-start gap-3">
                    <Users class="mt-0.5 h-5 w-5 text-muted-foreground" />
                    <div>
                        <p class="font-medium">Affiliate perks</p>
                        <p class="text-muted-foreground">Custom referral link, dashboard insights, and automated payouts.</p>
                    </div>
                </div>
            </div>
            <DialogFooter class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button variant="ghost" @click="dismiss">Maybe later</Button>
                <Button @click="requestAffiliate">Apply now</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
