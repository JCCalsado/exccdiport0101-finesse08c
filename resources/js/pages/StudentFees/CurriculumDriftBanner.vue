<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { AlertTriangle, RefreshCw, History, Plus, Minus, Pencil } from 'lucide-vue-next'

// ─── Types ────────────────────────────────────────────────────────────────────

interface DriftChangePayload {
  subject_id: number | null
  code: string
  name: string
  field?: string
  old?: unknown
  new?: unknown
}

interface DriftChange {
  event_type: 'subject_added' | 'subject_removed' | 'subject_changed'
  payload: DriftChangePayload
}

interface DriftResponse {
  is_manual: boolean
  has_drift: boolean
  changes: DriftChange[]
  can_apply: boolean
  curriculum_synced_at?: string | null
}

interface AssessmentEvent {
  id: number
  event_type: string
  payload: Record<string, unknown> | null
  reason: string | null
  changed_by: string
  created_at: string
}

const props = defineProps<{
  userId: number
  /** Passed straight from Show.vue's assessment.events prop — avoids a second
   *  round trip just to render history; drift itself still needs a live
   *  check since it compares against the *current* Subject table. */
  events: AssessmentEvent[]
  /** Server already knows this from assessment.generated_from — used only to
   *  skip the drift fetch entirely for manual-origin assessments instead of
   *  firing a request whose answer we already know will be "no drift concept
   *  applies here." */
  generatedFrom: 'curriculum' | 'manual'
}>()

// ─── Drift state ──────────────────────────────────────────────────────────────

const loading   = ref(false)
const drift     = ref<DriftResponse | null>(null)
const applying  = ref(false)
const fetchError = ref<string | null>(null)

async function fetchDrift() {
  if (props.generatedFrom === 'manual') return

  loading.value = true
  fetchError.value = null
  try {
    const { data } = await axios.get<DriftResponse>(
      route('student-fees.curriculum-drift', props.userId)
    )
    drift.value = data
  } catch (e) {
    // Non-fatal — the banner just doesn't render. The assessment itself is
    // unaffected; this is a read-only comparison, not part of the billing path.
    fetchError.value = 'Could not check curriculum status.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchDrift)

function applySync() {
  if (!drift.value?.can_apply) return

  const confirmed = window.confirm(
    'This will rebuild the subject list and recompute fees, tuition, and payment terms from the current curriculum. ' +
    'This cannot be undone. Continue?'
  )
  if (!confirmed) return

  applying.value = true
  router.post(
    route('student-fees.curriculum-sync', props.userId),
    {},
    {
      onFinish: () => { applying.value = false },
    }
  )
}

const addedChanges   = computed(() => (drift.value?.changes ?? []).filter(c => c.event_type === 'subject_added'))
const removedChanges = computed(() => (drift.value?.changes ?? []).filter(c => c.event_type === 'subject_removed'))
const fieldChanges   = computed(() => (drift.value?.changes ?? []).filter(c => c.event_type === 'subject_changed'))

// ─── Event history formatting ─────────────────────────────────────────────────

const showHistory = ref(false)

function describeEvent(e: AssessmentEvent): string {
  const p = e.payload ?? {}
  switch (e.event_type) {
    case 'created':
      return `Assessment created (${p.generated_from === 'manual' ? 'manual subject selection' : 'generated from curriculum'})`
    case 'subject_added':
      return `Subject added: ${p.code ?? ''} — ${p.name ?? ''}`
    case 'subject_removed':
      return `Subject removed: ${p.code ?? ''} — ${p.name ?? ''}`
    case 'subject_changed':
      return `${p.code ?? ''} ${p.field ?? 'field'} changed: ${p.old ?? '—'} → ${p.new ?? '—'}`
    case 'curriculum_synced':
      return `Synced with curriculum (total ₱${Number(p.total_before ?? 0).toLocaleString()} → ₱${Number(p.total_after ?? 0).toLocaleString()})`
    case 'fields_updated':
      return `Updated: ${Object.keys(p).join(', ')}`
    default:
      return e.event_type
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })
}
</script>

<template>
  <div class="space-y-3">
    <!-- Drift banner — only ever renders for curriculum-origin assessments -->
    <Card
      v-if="drift?.has_drift"
      class="border-amber-200 bg-amber-50"
    >
      <CardHeader class="pb-2">
        <CardTitle class="flex items-center gap-2 text-sm font-semibold text-amber-800">
          <AlertTriangle class="h-4 w-4" />
          Curriculum has changed since this assessment was built
        </CardTitle>
      </CardHeader>
      <CardContent class="space-y-3 text-sm text-amber-900">
        <ul class="space-y-1">
          <li v-for="(c, i) in addedChanges" :key="'add-' + i" class="flex items-center gap-2">
            <Plus class="h-3.5 w-3.5 text-green-700" />
            <span>{{ c.payload.code }} — {{ c.payload.name }} is now part of the curriculum</span>
          </li>
          <li v-for="(c, i) in removedChanges" :key="'rem-' + i" class="flex items-center gap-2">
            <Minus class="h-3.5 w-3.5 text-red-700" />
            <span>{{ c.payload.code }} — {{ c.payload.name }} is no longer part of the curriculum</span>
          </li>
          <li v-for="(c, i) in fieldChanges" :key="'chg-' + i" class="flex items-center gap-2">
            <Pencil class="h-3.5 w-3.5 text-amber-700" />
            <span>{{ c.payload.code }}: {{ c.payload.field }} changed from {{ c.payload.old }} to {{ c.payload.new }}</span>
          </li>
        </ul>

        <div class="flex items-center gap-3 pt-1">
          <Button
            size="sm"
            :disabled="!drift.can_apply || applying"
            @click="applySync"
          >
            <RefreshCw class="mr-1.5 h-3.5 w-3.5" :class="{ 'animate-spin': applying }" />
            {{ applying ? 'Applying…' : 'Apply Curriculum Changes' }}
          </Button>
          <span v-if="!drift.can_apply" class="text-xs text-amber-700">
            Locked — payments have already been recorded on this assessment.
          </span>
        </div>
      </CardContent>
    </Card>

    <!-- Event history (collapsed by default) -->
    <div v-if="events.length">
      <button
        type="button"
        class="flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-gray-700"
        @click="showHistory = !showHistory"
      >
        <History class="h-3.5 w-3.5" />
        {{ showHistory ? 'Hide' : 'Show' }} assessment history ({{ events.length }})
      </button>

      <ul v-if="showHistory" class="mt-2 space-y-1.5 border-l-2 border-gray-100 pl-3 text-xs text-gray-600">
        <li v-for="e in events" :key="e.id">
          <span class="font-medium text-gray-800">{{ describeEvent(e) }}</span>
          <span class="text-gray-400"> — {{ e.changed_by }}, {{ formatDate(e.created_at) }}</span>
        </li>
      </ul>
    </div>
  </div>
</template>