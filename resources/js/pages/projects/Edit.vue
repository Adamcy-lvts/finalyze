<!-- /resources/js/pages/projects/Edit.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue';
import TemplateVariablePicker from '@/components/ui/template-variable-picker/TemplateVariablePicker.vue';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { toTypedSchema } from '@vee-validate/zod';
import { Link, router } from '@inertiajs/vue3';
import { useUrlSearchParams } from '@vueuse/core';
import { AlertCircle, ArrowLeft, Check, ChevronRight, ChevronsUpDown, Loader2, Lock, Plus, Trash2, Save, FileText, GraduationCap, BookOpen, RotateCcw } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

interface Project {
    id: number;
    slug: string;
    title: string | null;
    topic: string | null;
    description: string | null;
    type: string;
    status: string;
    field_of_study: string;
    mode: string;
    university: string;
    full_university_name: string;
    faculty: string | null;
    course: string;
    supervisor_name: string | null;
    settings: {
        department?: string;
        matric_number?: string;
        academic_session?: string;
        ai_assistance_level?: string;
    };
    dedication: string | null;
    acknowledgements: string | null;
    abstract: string | null;
    declaration: string | null;
    certification: string | null;
    certification_signatories: Array<{ name: string; title: string }>;
    tables: Array<{ title: string; description?: string }>;
    abbreviations: Array<{ abbreviation: string; full_form: string }>;
    created_at: string;
}

interface Props {
    project: Project;
    preliminary_templates: Record<string, string>;
}

const props = defineProps<Props>();

// Helpers to map existing string labels to option values
const resolveSelectValue = (
    options: Array<{ value: string; label: string }>,
    incoming?: string | null
): string => {
    if (!incoming) return '';
    const byValue = options.find((o) => o.value === incoming);
    if (byValue) return incoming;
    const lowerIncoming = incoming.toLowerCase();
    const byLabel = options.find((o) => o.label.toLowerCase() === lowerIncoming);
    return byLabel?.value ?? incoming;
};

// Form state
const params = useUrlSearchParams('history');
const activeTab = ref((params.tab as string) || 'basic');

// Sync active tab with URL
watch(activeTab, (newVal) => {
    params.tab = newVal;
});

const processing = ref(false);
const isDirty = ref(false);

// Form schema with validation
const formSchema = toTypedSchema(
    z.object({
        // Basic Info
        description: z.string().max(1000).nullable(),
        field_of_study: z.string().nullable(),
        mode: z.enum(['auto', 'manual'], {
            required_error: 'Please select a working mode',
        }),

        // Academic Details - Institutional
        university: z.string().min(1, 'University is required'),
        faculty: z.string().min(1, 'Faculty is required'),
        course: z.string().min(1, 'Course is required'),
        supervisor_name: z.string().nullable(),

        // Academic Details - Student Info
        department: z.string().nullable(),
        matric_number: z.string().nullable(),
        academic_session: z.string().nullable(),

        // Preliminary Pages
        dedication: z.string().max(5000).nullable(),
        acknowledgements: z.string().max(10000).nullable(),
        abstract: z.string().max(5000).nullable(),
        declaration: z.string().max(5000).nullable(),
        certification: z.string().max(5000).nullable(),
    })
);

// Dynamic arrays
const signatories = ref<Array<{ name: string; title: string }>>(
    props.project.certification_signatories && props.project.certification_signatories.length > 0
        ? props.project.certification_signatories
        : [{ name: '', title: '' }]
);

const tables = ref<Array<{ title: string; description?: string }>>(
    props.project.tables && props.project.tables.length > 0
        ? props.project.tables
        : []
);

const abbreviations = ref<Array<{ abbreviation: string; full_form: string }>>(
    props.project.abbreviations && props.project.abbreviations.length > 0
        ? props.project.abbreviations
        : []
);

// Nigerian Universities List
const universities = [
    // Federal Universities
    { value: 'abu', label: 'Ahmadu Bello University (ABU), Zaria', fullName: 'Ahmadu Bello University, Zaria' },
    { value: 'ui', label: 'University of Ibadan (UI)', fullName: 'University of Ibadan' },
    { value: 'unn', label: 'University of Nigeria, Nsukka (UNN)', fullName: 'University of Nigeria, Nsukka' },
    { value: 'oau', label: 'Obafemi Awolowo University (OAU), Ile-Ife', fullName: 'Obafemi Awolowo University, Ile-Ife' },
    { value: 'unilag', label: 'University of Lagos (UNILAG)', fullName: 'University of Lagos' },
    { value: 'unical', label: 'University of Calabar (UNICAL)', fullName: 'University of Calabar' },
    { value: 'unijos', label: 'University of Jos (UNIJOS)', fullName: 'University of Jos' },
    { value: 'unimaid', label: 'University of Maiduguri (UNIMAID)', fullName: 'University of Maiduguri' },
    { value: 'uniben', label: 'University of Benin (UNIBEN)', fullName: 'University of Benin' },
    { value: 'uniport', label: 'University of Port Harcourt (UNIPORT)', fullName: 'University of Port Harcourt' },
    { value: 'buk', label: 'Bayero University, Kano (BUK)', fullName: 'Bayero University, Kano' },
    { value: 'uniuyo', label: 'University of Uyo (UNIUYO)', fullName: 'University of Uyo' },
    { value: 'uniilorin', label: 'University of Ilorin (UNILORIN)', fullName: 'University of Ilorin' },
    { value: 'futminna', label: 'Federal University of Technology, Minna (FUTMINNA)', fullName: 'Federal University of Technology, Minna' },
    { value: 'futa', label: 'Federal University of Technology, Akure (FUTA)', fullName: 'Federal University of Technology, Akure' },
    { value: 'futo', label: 'Federal University of Technology, Owerri (FUTO)', fullName: 'Federal University of Technology, Owerri' },
    { value: 'modibbo', label: 'Modibbo Adama University of Technology, Yola', fullName: 'Modibbo Adama University of Technology, Yola' },
    { value: 'uniabuja', label: 'University of Abuja (UNIABUJA)', fullName: 'University of Abuja' },
    { value: 'nda', label: 'Nigerian Defence Academy (NDA), Kaduna', fullName: 'Nigerian Defence Academy, Kaduna' },
    { value: 'funaab', label: 'Federal University of Agriculture, Abeokuta (FUNAAB)', fullName: 'Federal University of Agriculture, Abeokuta' },
    { value: 'fudutsinma', label: 'Federal University, Dutsin-Ma', fullName: 'Federal University, Dutsin-Ma' },
    { value: 'fugashua', label: 'Federal University, Gashua', fullName: 'Federal University, Gashua' },
    { value: 'fukashere', label: 'Federal University, Kashere', fullName: 'Federal University, Kashere' },
    { value: 'fulafia', label: 'Federal University, Lafia', fullName: 'Federal University, Lafia' },
    { value: 'fulokoja', label: 'Federal University, Lokoja', fullName: 'Federal University, Lokoja' },
    { value: 'funai', label: 'Federal University, Ndufu-Alike (FUNAI)', fullName: 'Federal University, Ndufu-Alike' },
    { value: 'fuotuoke', label: 'Federal University, Otuoke', fullName: 'Federal University, Otuoke' },
    { value: 'fuoye', label: 'Federal University, Oye-Ekiti (FUOYE)', fullName: 'Federal University, Oye-Ekiti' },
    { value: 'fuwukari', label: 'Federal University, Wukari', fullName: 'Federal University, Wukari' },

    // State Universities
    { value: 'lasu', label: 'Lagos State University (LASU)', fullName: 'Lagos State University' },
    { value: 'aaua', label: 'Adekunle Ajasin University, Akungba (AAUA)', fullName: 'Adekunle Ajasin University, Akungba' },
    { value: 'adsu', label: 'Adamawa State University, Mubi', fullName: 'Adamawa State University, Mubi' },
    { value: 'aksu', label: 'Akwa Ibom State University (AKSU)', fullName: 'Akwa Ibom State University' },
    { value: 'ambrose', label: 'Ambrose Alli University, Ekpoma', fullName: 'Ambrose Alli University, Ekpoma' },
    { value: 'ansu', label: 'Anambra State University, Uli', fullName: 'Anambra State University, Uli' },
    { value: 'basu', label: 'Bauchi State University, Gadau', fullName: 'Bauchi State University, Gadau' },
    { value: 'bsu', label: 'Benue State University, Makurdi', fullName: 'Benue State University, Makurdi' },
    { value: 'bosu', label: 'Bornu State University, Maiduguri', fullName: 'Bornu State University, Maiduguri' },
    { value: 'crutech', label: 'Cross River University of Technology (CRUTECH)', fullName: 'Cross River University of Technology' },
    { value: 'delsu', label: 'Delta State University, Abraka (DELSU)', fullName: 'Delta State University, Abraka' },
    { value: 'ebsu', label: 'Ebonyi State University, Abakaliki (EBSU)', fullName: 'Ebonyi State University, Abakaliki' },
    { value: 'edsu', label: 'Edo State University, Uzairue', fullName: 'Edo State University, Uzairue' },
    { value: 'eksu', label: 'Ekiti State University, Ado-Ekiti (EKSU)', fullName: 'Ekiti State University, Ado-Ekiti' },
    { value: 'esut', label: 'Enugu State University of Science and Technology (ESUT)', fullName: 'Enugu State University of Science and Technology' },
    { value: 'gombe', label: 'Gombe State University', fullName: 'Gombe State University' },
    { value: 'imsu', label: 'Imo State University, Owerri (IMSU)', fullName: 'Imo State University, Owerri' },
    { value: 'kasu', label: 'Kaduna State University (KASU)', fullName: 'Kaduna State University' },
    { value: 'kogi', label: 'Kogi State University, Anyigba', fullName: 'Kogi State University, Anyigba' },
    { value: 'kwasu', label: 'Kwara State University, Malete (KWASU)', fullName: 'Kwara State University, Malete' },
    {
        value: 'lautech',
        label: 'Ladoke Akintola University of Technology, Ogbomoso (LAUTECH)',
        fullName: 'Ladoke Akintola University of Technology, Ogbomoso',
    },
    {
        value: 'mouau',
        label: 'Michael Okpara University of Agriculture, Umudike (MOUAU)',
        fullName: 'Michael Okpara University of Agriculture, Umudike',
    },
    { value: 'nasarawa', label: 'Nasarawa State University, Keffi', fullName: 'Nasarawa State University, Keffi' },
    { value: 'noun', label: 'National Open University of Nigeria (NOUN)', fullName: 'National Open University of Nigeria' },
    { value: 'oou', label: 'Olabisi Onabanjo University, Ago-Iwoye (OOU)', fullName: 'Olabisi Onabanjo University, Ago-Iwoye' },
    { value: 'osun', label: 'Osun State University, Osogbo (UNIOSUN)', fullName: 'Osun State University, Osogbo' },
    { value: 'plasu', label: 'Plateau State University, Bokkos', fullName: 'Plateau State University, Bokkos' },
    {
        value: 'rsust',
        label: 'Rivers State University of Science and Technology (RSUST)',
        fullName: 'Rivers State University of Science and Technology',
    },
    { value: 'sokoto', label: 'Sokoto State University', fullName: 'Sokoto State University' },
    { value: 'tasued', label: 'Tai Solarin University of Education, Ijagun (TASUED)', fullName: 'Tai Solarin University of Education, Ijagun' },
    { value: 'unizik', label: 'Nnamdi Azikiwe University, Awka (UNIZIK)', fullName: 'Nnamdi Azikiwe University, Awka' },
    { value: 'ysu', label: 'Yobe State University, Damaturu', fullName: 'Yobe State University, Damaturu' },
    { value: 'zamfara', label: 'Zamfara State University', fullName: 'Zamfara State University' },

    // Private Universities
    { value: 'cu', label: 'Covenant University, Ota', fullName: 'Covenant University, Ota' },
    { value: 'babcock', label: 'Babcock University, Ilishan-Remo', fullName: 'Babcock University, Ilishan-Remo' },
    { value: 'aun', label: 'American University of Nigeria, Yola (AUN)', fullName: 'American University of Nigeria, Yola' },
    { value: 'adeleke', label: 'Adeleke University, Ede', fullName: 'Adeleke University, Ede' },
    { value: 'afe_babalola', label: 'Afe Babalola University, Ado-Ekiti (ABUAD)', fullName: 'Afe Babalola University, Ado-Ekiti' },
    { value: 'ajayi_crowther', label: 'Ajayi Crowther University, Oyo', fullName: 'Ajayi Crowther University, Oyo' },
    { value: 'al_qalam', label: 'Al-Qalam University, Katsina', fullName: 'Al-Qalam University, Katsina' },
    { value: 'al_hikmah', label: 'Al-Hikmah University, Ilorin', fullName: 'Al-Hikmah University, Ilorin' },
    { value: 'baze', label: 'Baze University, Abuja', fullName: 'Baze University, Abuja' },
    { value: 'bells', label: 'Bells University of Technology, Ota', fullName: 'Bells University of Technology, Ota' },
    { value: 'bingham', label: 'Bingham University, Karu', fullName: 'Bingham University, Karu' },
    { value: 'bowen', label: 'Bowen University, Iwo', fullName: 'Bowen University, Iwo' },
    { value: 'caleb', label: 'Caleb University, Lagos', fullName: 'Caleb University, Lagos' },
    { value: 'crawford', label: 'Crawford University, Igbesa', fullName: 'Crawford University, Igbesa' },
    { value: 'crescent', label: 'Crescent University, Abeokuta', fullName: 'Crescent University, Abeokuta' },
    { value: 'elizade', label: 'Elizade University, Ilara-Mokin', fullName: 'Elizade University, Ilara-Mokin' },
    { value: 'fountain', label: 'Fountain University, Osogbo', fullName: 'Fountain University, Osogbo' },
    { value: 'igbinedion', label: 'Igbinedion University, Okada', fullName: 'Igbinedion University, Okada' },
    { value: 'landmark', label: 'Landmark University, Omu-Aran', fullName: 'Landmark University, Omu-Aran' },
    { value: 'lead_city', label: 'Lead City University, Ibadan', fullName: 'Lead City University, Ibadan' },
    { value: 'madonna', label: 'Madonna University, Okija', fullName: 'Madonna University, Okija' },
    { value: 'mcpherson', label: 'McPherson University, Seriki-Sotayo', fullName: 'McPherson University, Seriki-Sotayo' },
    { value: 'mountain_top', label: 'Mountain Top University, Ibafo', fullName: 'Mountain Top University, Ibafo' },
    { value: 'nile', label: 'Nile University of Nigeria, Abuja', fullName: 'Nile University of Nigeria, Abuja' },
    { value: 'oduduwa', label: 'Oduduwa University, Ipetumodu', fullName: 'Oduduwa University, Ipetumodu' },
    { value: 'pan_atlantic', label: 'Pan-Atlantic University, Lagos', fullName: 'Pan-Atlantic University, Lagos' },
    { value: 'paul', label: 'Paul University, Awka', fullName: 'Paul University, Awka' },
    { value: 'redeemers', label: "Redeemer's University, Ede", fullName: "Redeemer's University, Ede" },
    { value: 'rhema', label: 'Rhema University, Obeama-Asa', fullName: 'Rhema University, Obeama-Asa' },
    { value: 'salem', label: 'Salem University, Lokoja', fullName: 'Salem University, Lokoja' },
    { value: 'samuel_adegboyega', label: 'Samuel Adegboyega University, Ogwa', fullName: 'Samuel Adegboyega University, Ogwa' },
    { value: 'southwestern', label: 'Southwestern University, Okun-Owa', fullName: 'Southwestern University, Okun-Owa' },
    { value: 'summit', label: 'Summit University, Offa', fullName: 'Summit University, Offa' },
    { value: 'veritas', label: 'Veritas University, Abuja', fullName: 'Veritas University, Abuja' },
    { value: 'wellspring', label: 'Wellspring University, Evbuobanosa', fullName: 'Wellspring University, Evbuobanosa' },
    { value: 'western_delta', label: 'Western Delta University, Oghara', fullName: 'Western Delta University, Oghara' },
    { value: 'other', label: 'Other', fullName: 'Other' },
] as const;

// Nigerian University Faculties
const faculties = [
    { value: 'agriculture', label: 'Faculty of Agriculture' },
    { value: 'arts', label: 'Faculty of Arts' },
    { value: 'basic_medical_sciences', label: 'Faculty of Basic Medical Sciences' },
    { value: 'clinical_sciences', label: 'Faculty of Clinical Sciences' },
    { value: 'communication_and_media_studies', label: 'Faculty of Communication and Media Studies' },
    { value: 'dentistry', label: 'Faculty of Dentistry' },
    { value: 'earth_sciences', label: 'Faculty of Earth Sciences' },
    { value: 'education', label: 'Faculty of Education' },
    { value: 'engineering', label: 'Faculty of Engineering' },
    { value: 'environmental_sciences', label: 'Faculty of Environmental Sciences' },
    { value: 'law', label: 'Faculty of Law' },
    { value: 'life_sciences', label: 'Faculty of Life Sciences' },
    { value: 'management_sciences', label: 'Faculty of Management Sciences' },
    { value: 'medicine', label: 'Faculty of Medicine' },
    { value: 'nursing', label: 'Faculty of Nursing' },
    { value: 'pharmacy', label: 'Faculty of Pharmacy' },
    { value: 'physical_sciences', label: 'Faculty of Physical Sciences' },
    { value: 'sciences', label: 'Faculty of Sciences' },
    { value: 'social_sciences', label: 'Faculty of Social Sciences' },
    { value: 'technology', label: 'Faculty of Technology' },
    { value: 'veterinary_medicine', label: 'Faculty of Veterinary Medicine' },
] as const;

// Reactive form values that persist across tab switches
const formValues = ref({
    description: props.project.description || '',
    field_of_study: props.project.field_of_study || '',
    mode: props.project.mode || 'auto',
    university: resolveSelectValue(universities as any, props.project.university),
    faculty: resolveSelectValue(faculties as any, props.project.faculty),
    course: props.project.course || '',
    supervisor_name: props.project.supervisor_name || '',
    department: props.project.settings?.department || '',
    matric_number: props.project.settings?.matric_number || '',
    academic_session: props.project.settings?.academic_session || '',
    dedication: props.project.dedication || '',
    acknowledgements: props.project.acknowledgements || '',
    abstract: props.project.abstract || '',
    declaration: props.project.declaration || '',
    certification: props.project.certification || '',
});

// Refs for RichTextEditor instances
const dedicationEditor = ref<InstanceType<typeof RichTextEditor> | null>(null);
const acknowledgementsEditor = ref<InstanceType<typeof RichTextEditor> | null>(null);
const abstractEditor = ref<InstanceType<typeof RichTextEditor> | null>(null);
const declarationEditor = ref<InstanceType<typeof RichTextEditor> | null>(null);
const certificationEditor = ref<InstanceType<typeof RichTextEditor> | null>(null);

// Popover state for selects
const universityPopoverOpen = ref(false);
const facultyPopoverOpen = ref(false);

// Default templates (from backend)
const defaultTemplates = computed<Record<string, string>>(() => props.preliminary_templates || {});

// Template variable insertion
const insertVariable = (editorRef: any, variableName: string) => {
    if (editorRef.value) {
        const formattedVariable = `{{${variableName}}}`;
        editorRef.value.replaceSelection(formattedVariable);
        editorRef.value.focus();
    }
};

// Load default template
const loadDefaultTemplate = (field: 'dedication' | 'acknowledgements' | 'abstract' | 'declaration' | 'certification') => {
    formValues.value[field] = defaultTemplates.value[field] || '';
    isDirty.value = true;
    toast('Template Loaded', {
        description: `Default ${field} template has been loaded. You can now customize it.`,
    });
};

// Type badge variant helper
const getTypeBadgeVariant = computed(() => {
    const type = props.project.type;
    return type === 'undergraduate' ? 'default' : type === 'postgraduate' ? 'secondary' : 'outline';
});

// Status badge variant helper
const getStatusBadgeVariant = computed(() => {
    const status = props.project.status;
    if (status === 'completed') return 'default';
    if (status === 'writing') return 'secondary';
    return 'outline';
});

// Form submission handler
const onSubmit = (values: Record<string, any>) => {
    processing.value = true;

    const { department, matric_number, academic_session, ...rest } = values;

    const data = {
        ...rest,
        // Include preliminary pages from formValues since RichTextEditor binds directly to it
        dedication: formValues.value.dedication,
        acknowledgements: formValues.value.acknowledgements,
        abstract: formValues.value.abstract,
        declaration: formValues.value.declaration,
        certification: formValues.value.certification,
        settings: {
            department,
            matric_number,
            academic_session,
            ai_assistance_level: props.project.settings?.ai_assistance_level || 'moderate',
        },
        certification_signatories: signatories.value.filter((s) => s.name && s.title),
        tables: tables.value.filter((t) => t.title),
        abbreviations: abbreviations.value.filter((a) => a.abbreviation && a.full_form),
    };

    router.patch(route('projects.update', props.project.slug), data, {
        preserveScroll: true,
        onSuccess: () => {
            toast('Success!', {
                description: 'Project details updated successfully',
            });
            isDirty.value = false;
        },
        onError: (errors) => {
            console.error('Update errors:', errors);
            toast('Update Failed', {
                description: 'Please check the form for errors and try again.',
            });
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};


// Array management helpers
const addSignatory = () => {
    signatories.value.push({ name: '', title: '' });
    isDirty.value = true;
};

const removeSignatory = (index: number) => {
    if (signatories.value.length > 1) {
        signatories.value.splice(index, 1);
        isDirty.value = true;
    }
};

const addTable = () => {
    tables.value.push({ title: '', description: '' });
    isDirty.value = true;
};

const removeTable = (index: number) => {
    tables.value.splice(index, 1);
    isDirty.value = true;
};

const addAbbreviation = () => {
    abbreviations.value.push({ abbreviation: '', full_form: '' });
    isDirty.value = true;
};

const removeAbbreviation = (index: number) => {
    abbreviations.value.splice(index, 1);
    isDirty.value = true;
};

// Dirty form detection - warn before leaving
const handleBeforeUnload = (e: BeforeUnloadEvent) => {
    if (isDirty.value) {
        e.preventDefault();
        e.returnValue = '';
    }
};

// Set up and tear down beforeunload handler
if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', handleBeforeUnload);
}

onBeforeUnmount(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('beforeunload', handleBeforeUnload);
    }
});
</script>

<template>
    <AppLayout title="Edit Project">
        <Form v-slot="{ meta }" :validation-schema="formSchema" :initial-values="formValues"
            @submit="onSubmit" @invalid-submit="onSubmit" keep-values>
            <div class="min-h-screen bg-muted/10 pb-20">
                <div class="mx-auto max-w-5xl space-y-6 p-4 md:space-y-8 md:p-6 lg:p-10">
                    <!-- Header Section -->
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <Link :href="route('projects.show', project.slug)"
                                    class="hover:text-primary transition-colors flex items-center gap-1">
                                <ArrowLeft class="h-3 w-3" />
                                Back to Project
                                </Link>
                                <span class="text-muted-foreground/40">/</span>
                                <span class="text-foreground font-medium">Edit Details</span>
                            </div>
                            <h1 class="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                                Edit Project
                            </h1>
                            <p class="text-muted-foreground max-w-2xl">
                                Update your project information. Some institutional fields are protected to maintain
                                project
                                integrity.
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Button variant="outline" @click="router.visit(route('projects.show', project.slug))"
                                :disabled="processing" class="bg-background">
                                Cancel
                            </Button>
                            <Button type="submit" :disabled="processing" class="min-w-[140px]">
                                <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                                <Save v-else class="mr-2 h-4 w-4" />
                                Save Changes
                            </Button>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="grid gap-8">
                        <Tabs v-model="activeTab" class="w-full space-y-6">
                            <div
                                class="sticky top-0 z-10 -mx-6 bg-muted/10 px-6 backdrop-blur-sm md:static md:mx-0 md:bg-transparent md:p-0">
                                <TabsList
                                    class="grid w-full grid-cols-1 h-auto gap-2 rounded-xl bg-muted/60 p-1.5 text-muted-foreground sm:h-14 sm:grid-cols-3 sm:gap-2 border border-white/10 shadow-inner">
                                    <TabsTrigger value="basic"
                                        class="rounded-lg px-3 py-2.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-blue-600 data-[state=active]:text-white data-[state=active]:shadow-md data-[state=active]:font-bold">
                                        <FileText class="mr-2 h-4 w-4" />
                                        Basic Info
                                    </TabsTrigger>
                                    <TabsTrigger value="academic"
                                        class="rounded-lg px-3 py-2.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-blue-600 data-[state=active]:text-white data-[state=active]:shadow-md data-[state=active]:font-bold">
                                        <GraduationCap class="mr-2 h-4 w-4" />
                                        Academic Details
                                    </TabsTrigger>
                                    <TabsTrigger value="preliminary"
                                        class="rounded-lg px-3 py-2.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-blue-600 data-[state=active]:text-white data-[state=active]:shadow-md data-[state=active]:font-bold">
                                        <BookOpen class="mr-2 h-4 w-4" />
                                        Preliminary Pages
                                    </TabsTrigger>
                                </TabsList>
                            </div>

                            <!-- TAB 1: BASIC INFO -->
                            <TabsContent value="basic" class="space-y-6 focus-visible:outline-none">
                                <Card class="border-none shadow-md ring-1 ring-black/5">
                                    <CardHeader>
                                        <CardTitle>Project Overview</CardTitle>
                                        <CardDescription>Basic information about your research project.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="space-y-8">
                                        <!-- Readonly Fields Display -->
                                        <div class="rounded-xl border bg-muted/30 p-5">
                                            <div class="mb-4 flex items-center gap-2 text-primary">
                                                <Lock class="h-4 w-4" />
                                                <span class="text-sm font-semibold">Protected Fields</span>
                                            </div>

                                            <div class="grid gap-6 md:grid-cols-2">
                                                <div class="space-y-1.5">
                                                    <label
                                                        class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Project
                                                        Type</label>
                                                    <div>
                                                        <Badge :variant="getTypeBadgeVariant" class="px-3 py-1">
                                                            {{ project.type.toUpperCase() }}
                                                        </Badge>
                                                    </div>
                                                </div>

                                                <div class="space-y-1.5">
                                                    <label
                                                        class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Status</label>
                                                    <div>
                                                        <Badge :variant="getStatusBadgeVariant" class="px-3 py-1">
                                                            {{ project.status.replace('_', ' ').toUpperCase() }}
                                                        </Badge>
                                                    </div>
                                                </div>

                                                <div class="md:col-span-2 space-y-1.5">
                                                    <label
                                                        class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Title
                                                        / Topic</label>
                                                    <SafeHtmlText
                                                        as="p"
                                                        class="text-lg font-medium text-foreground"
                                                        :content="project.title || project.topic || 'No title set'"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Editable Fields -->
                                        <div class="grid gap-6">
                                            <FormField v-slot="{ componentField }" name="description">
                                                <FormItem>
                                                    <FormLabel>Description</FormLabel>
                                                    <FormControl>
                                                        <Textarea
                                                            :value="componentField.modelValue ?? ''"
                                                            placeholder="Brief description of your project..." rows="4"
                                                            class="resize-none" @input="
                                                                (e: any) => {
                                                                    const val = e?.target?.value ?? '';
                                                                    componentField['onUpdate:modelValue']?.(val);
                                                                    formValues.description = val;
                                                                    isDirty = true;
                                                                }
                                                            " />
                                                    </FormControl>
                                                    <FormDescription>
                                                        A brief overview of your project (optional, max 1000 characters)
                                                    </FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <div class="grid gap-6 md:grid-cols-2">
                                                <FormField v-slot="{ componentField }" name="field_of_study">
                                                    <FormItem>
                                                        <FormLabel>Field of Study *</FormLabel>
                                                        <FormControl>
                                                            <Input :value="componentField.modelValue"
                                                                placeholder="e.g., Computer Science" @input="
                                                                    (e: any) => {
                                                                        const val = e?.target?.value || '';
                                                                        componentField['onUpdate:modelValue']?.(val);
                                                                        formValues.field_of_study = val;
                                                                        isDirty = true;
                                                                    }
                                                                " />
                                                        </FormControl>
                                                        <FormMessage />
                                                    </FormItem>
                                                </FormField>

                                                <FormField v-slot="{ componentField }" name="mode">
                                                    <FormItem>
                                                        <FormLabel>Working Mode *</FormLabel>
                                                        <Select :model-value="componentField.modelValue"
                                                            @update:model-value="(val) => { componentField['onUpdate:modelValue']?.(val); formValues.mode = val; isDirty = true; }">
                                                            <FormControl>
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Select writing mode" />
                                                                </SelectTrigger>
                                                            </FormControl>
                                                            <SelectContent>
                                                                <SelectGroup>
                                                                    <SelectItem value="auto">AI Generated
                                                                    </SelectItem>
                                                                    <SelectItem value="manual">AI Assisted
                                                                    </SelectItem>
                                                                </SelectGroup>
                                                            </SelectContent>
                                                        </Select>
                                                        <FormMessage />
                                                    </FormItem>
                                                </FormField>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <!-- TAB 2: ACADEMIC DETAILS -->
                            <TabsContent value="academic" class="space-y-6 focus-visible:outline-none">
                                <Card class="border-none shadow-md ring-1 ring-black/5">
                                    <CardHeader>
                                        <CardTitle>Academic Information</CardTitle>
                                        <CardDescription>Details about your institution and course.</CardDescription>
                                    </CardHeader>
                                    <CardContent class="space-y-8">
                                        <!-- Institutional Fields with Warning -->
                                        <div class="space-y-6 rounded-xl border border-warning/50 bg-warning/5 p-5">
                                            <div class="flex items-center gap-3 text-warning">
                                                <AlertCircle class="h-5 w-5" />
                                                <div>
                                                    <p class="font-medium">Institutional Details</p>
                                                    <p class="text-sm text-muted-foreground">
                                                        Changes to these fields may affect your project structure and
                                                        guidance.
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="grid gap-6 md:grid-cols-2">
                                                <FormField v-slot="{ componentField }" name="university"
                                                    class="md:col-span-2">
                                                    <FormItem class="flex flex-col">
                                                        <FormLabel>University *</FormLabel>
                                                        <Popover v-model:open="universityPopoverOpen">
                                                            <PopoverTrigger as-child>
                                                                <FormControl>
                                                                    <Button variant="outline" role="combobox" :class="cn(
                                                                        'w-full justify-between',
                                                                        !componentField.modelValue && 'text-muted-foreground',
                                                                    )
                                                                        ">
                                                                        {{
                                                                            componentField.modelValue
                                                                                ? universities.find(
                                                                                    (university) => university.value ===
                                                                                        componentField.modelValue,
                                                                                )?.label
                                                                                : 'Select your university...'
                                                                        }}
                                                                        <ChevronsUpDown
                                                                            class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                                    </Button>
                                                                </FormControl>
                                                            </PopoverTrigger>
                                                            <PopoverContent class="w-full p-0" align="start">
                                                                <Command>
                                                                    <CommandInput placeholder="Search university..." />
                                                                    <CommandEmpty>No university found.</CommandEmpty>
                                                                    <CommandList>
                                                                        <CommandGroup>
                                                                            <CommandItem
                                                                                v-for="university in universities"
                                                                                :key="university.value"
                                                                                :value="university.label" @select="
                                                                                    () => {
                                                                                        componentField['onUpdate:modelValue']?.(university.value);
                                                                                        formValues.university = university.value;
                                                                                        isDirty = true;
                                                                                        universityPopoverOpen = false;
                                                                                    }
                                                                                ">
                                                                                {{ university.label }}
                                                                                <Check :class="cn(
                                                                                    'ml-auto h-4 w-4',
                                                                                    university.value === componentField.modelValue
                                                                                        ? 'opacity-100'
                                                                                        : 'opacity-0',
                                                                                )
                                                                                    " />
                                                                            </CommandItem>
                                                                        </CommandGroup>
                                                                    </CommandList>
                                                                </Command>
                                                            </PopoverContent>
                                                        </Popover>
                                                        <FormMessage />
                                                    </FormItem>
                                                </FormField>

                                                <FormField v-slot="{ componentField }" name="faculty">
                                                    <FormItem class="flex flex-col">
                                                        <FormLabel>Faculty *</FormLabel>
                                                        <Popover v-model:open="facultyPopoverOpen">
                                                            <PopoverTrigger as-child>
                                                                <FormControl>
                                                                    <Button variant="outline" role="combobox" :class="cn(
                                                                        'w-full justify-between',
                                                                        !componentField.modelValue && 'text-muted-foreground',
                                                                    )
                                                                        ">
                                                                        {{
                                                                            componentField.modelValue
                                                                                ? faculties.find((faculty) => faculty.value ===
                                                                                    componentField.modelValue)
                                                                                    ?.label
                                                                                : 'Select faculty...'
                                                                        }}
                                                                        <ChevronsUpDown
                                                                            class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                                    </Button>
                                                                </FormControl>
                                                            </PopoverTrigger>
                                                            <PopoverContent class="w-[300px] p-0">
                                                                <Command>
                                                                    <CommandInput placeholder="Search faculty..." />
                                                                    <CommandEmpty>No faculty found.</CommandEmpty>
                                                                    <CommandList>
                                                                        <CommandGroup>
                                                                            <CommandItem v-for="faculty in faculties"
                                                                                :key="faculty.value"
                                                                                :value="faculty.value" @select="
                                                                                    () => {
                                                                                        componentField['onUpdate:modelValue']?.(faculty.value);
                                                                                        formValues.faculty = faculty.value;
                                                                                        isDirty = true;
                                                                                        facultyPopoverOpen = false;
                                                                                    }
                                                                                ">
                                                                                <Check :class="cn(
                                                                                    'mr-2 h-4 w-4',
                                                                                    componentField.modelValue === faculty.value
                                                                                        ? 'opacity-100'
                                                                                        : 'opacity-0',
                                                                                )
                                                                                    " />
                                                                                {{ faculty.label }}
                                                                            </CommandItem>
                                                                        </CommandGroup>
                                                                    </CommandList>
                                                                </Command>
                                                            </PopoverContent>
                                                        </Popover>
                                                        <FormMessage />
                                                    </FormItem>
                                                </FormField>

                                                <FormField v-slot="{ componentField }" name="course">
                                                    <FormItem>
                                                        <FormLabel>Course *</FormLabel>
                                                        <FormControl>
                                                            <Input :value="componentField.modelValue"
                                                                placeholder="e.g., Computer Science" @input="
                                                                    (e: any) => {
                                                                        const val = e?.target?.value || '';
                                                                        componentField['onUpdate:modelValue']?.(val);
                                                                        formValues.course = val;
                                                                        isDirty = true;
                                                                    }
                                                                " />
                                                        </FormControl>
                                                        <FormMessage />
                                                    </FormItem>
                                                </FormField>
                                            </div>
                                        </div>

                                        <!-- Student & Supervisor Information -->
                                        <div class="grid gap-6 md:grid-cols-2">
                                            <FormField v-slot="{ componentField }" name="supervisor_name">
                                                <FormItem>
                                                    <FormLabel>Supervisor Name</FormLabel>
                                                    <FormControl>
                                                        <Input
                                                            :value="componentField.modelValue ?? ''"
                                                            placeholder="e.g., Dr. Jane Smith"
                                                            @input="
                                                                (e: any) => {
                                                                    const val = e?.target?.value ?? '';
                                                                    componentField['onUpdate:modelValue']?.(val);
                                                                    formValues.supervisor_name = val;
                                                                    isDirty = true;
                                                                }
                                                            " />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <FormField v-slot="{ componentField }" name="department">
                                                <FormItem>
                                                    <FormLabel>Department</FormLabel>
                                                    <FormControl>
                                                        <Input
                                                            :value="componentField.modelValue ?? ''"
                                                            placeholder="Department name"
                                                            @input="
                                                                (e: any) => {
                                                                    const val = e?.target?.value ?? '';
                                                                    componentField['onUpdate:modelValue']?.(val);
                                                                    formValues.department = val;
                                                                    isDirty = true;
                                                                }
                                                            " />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <FormField v-slot="{ componentField }" name="matric_number">
                                                <FormItem>
                                                    <FormLabel>Student ID / Matric Number</FormLabel>
                                                    <FormControl>
                                                        <Input
                                                            :value="componentField.modelValue ?? ''"
                                                            placeholder="e.g., 2019/123456"
                                                            @input="
                                                                (e: any) => {
                                                                    const val = e?.target?.value ?? '';
                                                                    componentField['onUpdate:modelValue']?.(val);
                                                                    formValues.matric_number = val;
                                                                    isDirty = true;
                                                                }
                                                            " />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <FormField v-slot="{ componentField }" name="academic_session">
                                                <FormItem>
                                                    <FormLabel>Academic Session</FormLabel>
                                                    <FormControl>
                                                        <Input
                                                            :value="componentField.modelValue ?? ''"
                                                            placeholder="e.g., 2023/2024"
                                                            @input="
                                                                (e: any) => {
                                                                    const val = e?.target?.value ?? '';
                                                                    componentField['onUpdate:modelValue']?.(val);
                                                                    formValues.academic_session = val;
                                                                    isDirty = true;
                                                                }
                                                            " />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <!-- TAB 3: PRELIMINARY PAGES -->
                            <TabsContent value="preliminary" class="space-y-6 focus-visible:outline-none">
                                <Card class="border-none shadow-md ring-1 ring-black/5">
                                    <CardHeader>
                                        <CardTitle>Preliminary Pages</CardTitle>
                                        <CardDescription>Use the rich text editor to customize your preliminary pages. Insert variables for dynamic content that will be filled automatically.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="space-y-8">
                                        <div class="space-y-8">
                                            <!-- Dedication -->
                                            <FormField name="dedication">
                                                <FormItem>
                                                    <div class="flex items-center justify-between mb-2">
                                                        <FormLabel>Dedication</FormLabel>
                                                        <div class="flex items-center gap-2">
                                                            <TemplateVariablePicker
                                                                @variable-selected="(v) => insertVariable(dedicationEditor, v)" />
                                                            <Button variant="outline" size="sm" type="button"
                                                                @click="loadDefaultTemplate('dedication')">
                                                                <RotateCcw class="h-4 w-4 mr-2" />
                                                                Load Template
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <FormControl>
                                                        <RichTextEditor ref="dedicationEditor" v-model="formValues.dedication"
                                                            placeholder="Dedicate your work to someone special..."
                                                            :min-height="'200px'"
                                                            @update:model-value="isDirty = true" />
                                                    </FormControl>
                                                    <FormDescription>Use template variables like &#123;&#123;supervisor_name&#125;&#125; for dynamic content</FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <!-- Acknowledgements -->
                                            <FormField name="acknowledgements">
                                                <FormItem>
                                                    <div class="flex items-center justify-between mb-2">
                                                        <FormLabel>Acknowledgements</FormLabel>
                                                        <div class="flex items-center gap-2">
                                                            <TemplateVariablePicker
                                                                @variable-selected="(v) => insertVariable(acknowledgementsEditor, v)" />
                                                            <Button variant="outline" size="sm" type="button"
                                                                @click="loadDefaultTemplate('acknowledgements')">
                                                                <RotateCcw class="h-4 w-4 mr-2" />
                                                                Load Template
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <FormControl>
                                                        <RichTextEditor ref="acknowledgementsEditor"
                                                            v-model="formValues.acknowledgements"
                                                            placeholder="Acknowledge those who helped you..."
                                                            :min-height="'300px'"
                                                            @update:model-value="isDirty = true" />
                                                    </FormControl>
                                                    <FormDescription>Include mentors, supervisors, family, and contributors</FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <!-- Abstract -->
                                            <FormField name="abstract">
                                                <FormItem>
                                                    <div class="flex items-center justify-between mb-2">
                                                        <FormLabel>Abstract</FormLabel>
                                                        <div class="flex items-center gap-2">
                                                            <TemplateVariablePicker
                                                                @variable-selected="(v) => insertVariable(abstractEditor, v)" />
                                                            <Button variant="outline" size="sm" type="button"
                                                                @click="loadDefaultTemplate('abstract')">
                                                                <RotateCcw class="h-4 w-4 mr-2" />
                                                                Load Template
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <FormControl>
                                                        <RichTextEditor ref="abstractEditor" v-model="formValues.abstract"
                                                            placeholder="Write your project abstract..."
                                                            :min-height="'350px'"
                                                            @update:model-value="isDirty = true" />
                                                    </FormControl>
                                                    <FormDescription>Concise summary of your research objectives, methods, findings, and conclusions</FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <!-- Declaration -->
                                            <FormField name="declaration">
                                                <FormItem>
                                                    <div class="flex items-center justify-between mb-2">
                                                        <FormLabel>Declaration</FormLabel>
                                                        <div class="flex items-center gap-2">
                                                            <TemplateVariablePicker
                                                                @variable-selected="(v) => insertVariable(declarationEditor, v)" />
                                                            <Button variant="outline" size="sm" type="button"
                                                                @click="loadDefaultTemplate('declaration')">
                                                                <RotateCcw class="h-4 w-4 mr-2" />
                                                                Load Template
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <FormControl>
                                                        <RichTextEditor ref="declarationEditor" v-model="formValues.declaration"
                                                            placeholder="Declare your work's originality..."
                                                            :min-height="'250px'"
                                                            @update:model-value="isDirty = true" />
                                                    </FormControl>
                                                    <FormDescription>Student's declaration of originality and academic integrity</FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <!-- Certification -->
                                            <FormField name="certification">
                                                <FormItem>
                                                    <div class="flex items-center justify-between mb-2">
                                                        <FormLabel>Certification</FormLabel>
                                                        <div class="flex items-center gap-2">
                                                            <TemplateVariablePicker
                                                                @variable-selected="(v) => insertVariable(certificationEditor, v)" />
                                                            <Button variant="outline" size="sm" type="button"
                                                                @click="loadDefaultTemplate('certification')">
                                                                <RotateCcw class="h-4 w-4 mr-2" />
                                                                Load Template
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <FormControl>
                                                        <RichTextEditor ref="certificationEditor" v-model="formValues.certification"
                                                            placeholder="Supervisor's certification of the work..."
                                                            :min-height="'250px'"
                                                            @update:model-value="isDirty = true" />
                                                    </FormControl>
                                                    <FormDescription>Supervisor's certification that the work meets academic standards</FormDescription>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>
                                        </div>

                                        <!-- Dynamic Sections -->
                                        <div class="grid gap-8 lg:grid-cols-2">
                                            <!-- Signatories -->
                                            <div class="space-y-4">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <h3 class="text-sm font-medium">Certification Signatories</h3>
                                                        <p class="text-xs text-muted-foreground">People who will sign
                                                            your certification.</p>
                                                    </div>
                                                    <Button variant="outline" size="sm" @click="addSignatory">
                                                        <Plus class="mr-2 h-3 w-3" />
                                                        Add
                                                    </Button>
                                                </div>
                                                <div class="space-y-3">
                                                    <div v-for="(signatory, index) in signatories" :key="index"
                                                        class="relative rounded-lg border bg-card p-3 shadow-sm transition-all hover:shadow-md">
                                                        <div class="grid gap-2 sm:grid-cols-2">
                                                            <Input v-model="signatory.name"
                                                                placeholder="Name (e.g., Dr. John Doe)"
                                                                class="h-8 text-sm" @input="isDirty = true" />
                                                            <Input v-model="signatory.title"
                                                                placeholder="Title (e.g., Supervisor)"
                                                                class="h-8 text-sm" @input="isDirty = true" />
                                                        </div>
                                                        <Button v-if="signatories.length > 1" variant="ghost"
                                                            size="icon"
                                                            class="absolute -right-2 -top-2 h-6 w-6 rounded-full bg-muted text-muted-foreground hover:bg-destructive hover:text-destructive-foreground"
                                                            @click="removeSignatory(index)">
                                                            <Trash2 class="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Tables -->
                                            <div class="space-y-4">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <h3 class="text-sm font-medium">List of Tables</h3>
                                                        <p class="text-xs text-muted-foreground">Tables appearing in
                                                            your project.</p>
                                                    </div>
                                                    <Button variant="outline" size="sm" @click="addTable">
                                                        <Plus class="mr-2 h-3 w-3" />
                                                        Add
                                                    </Button>
                                                </div>
                                                <div class="space-y-3">
                                                    <div v-for="(table, index) in tables" :key="index"
                                                        class="relative rounded-lg border bg-card p-3 shadow-sm transition-all hover:shadow-md">
                                                        <div class="grid gap-2 sm:grid-cols-2">
                                                            <Input v-model="table.title" placeholder="Table Title"
                                                                class="h-8 text-sm" @input="isDirty = true" />
                                                            <Input v-model="table.description"
                                                                placeholder="Description (Optional)" class="h-8 text-sm"
                                                                @input="isDirty = true" />
                                                        </div>
                                                        <Button variant="ghost" size="icon"
                                                            class="absolute -right-2 -top-2 h-6 w-6 rounded-full bg-muted text-muted-foreground hover:bg-destructive hover:text-destructive-foreground"
                                                            @click="removeTable(index)">
                                                            <Trash2 class="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Abbreviations -->
                                            <div class="lg:col-span-2 space-y-4">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <h3 class="text-sm font-medium">Abbreviations</h3>
                                                        <p class="text-xs text-muted-foreground">Define abbreviations
                                                            used.</p>
                                                    </div>
                                                    <Button variant="outline" size="sm" @click="addAbbreviation">
                                                        <Plus class="mr-2 h-3 w-3" />
                                                        Add
                                                    </Button>
                                                </div>
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <div v-for="(abbr, index) in abbreviations" :key="index"
                                                        class="relative rounded-lg border bg-card p-3 shadow-sm transition-all hover:shadow-md">
                                                        <div class="flex flex-col gap-2 sm:flex-row">
                                                            <Input v-model="abbr.abbreviation"
                                                                placeholder="Abbr (e.g. AI)" class="h-8 text-sm sm:w-24"
                                                                @input="isDirty = true" />
                                                            <Input v-model="abbr.full_form" placeholder="Full Form"
                                                                class="h-8 flex-1 text-sm" @input="isDirty = true" />
                                                        </div>
                                                        <Button variant="ghost" size="icon"
                                                            class="absolute -right-2 -top-2 h-6 w-6 rounded-full bg-muted text-muted-foreground hover:bg-destructive hover:text-destructive-foreground"
                                                            @click="removeAbbreviation(index)">
                                                            <Trash2 class="h-3 w-3" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </Form>
    </AppLayout>
</template>
