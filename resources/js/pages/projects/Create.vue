<!-- resources/js/Pages/Projects/Create.vue -->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Stepper, StepperDescription, StepperItem, StepperTitle, StepperTrigger } from '@/components/ui/stepper';
import WizardDebugPanel from '@/components/WizardDebugPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { BookOpen, Check, ChevronsUpDown, FileText, GraduationCap, School } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import * as z from 'zod';

// Props
interface ProjectCategory {
    id: number;
    slug: string;
    name: string;
    description: string;
    academic_levels: string[];
    default_chapter_count: number;
    target_word_count: number;
    target_duration: string;
}

// Step-aware data types
interface WizardData {
    format_version: string;
    steps: Record<
        string,
        {
            data: Record<string, any>;
            completed: boolean;
            timestamp: string | null;
        }
    >;
    current_step: number;
    furthest_completed_step: number;
}

interface ResumeProject {
    id: number;
    current_step: number;
    wizard_data: WizardData;
}

interface Props {
    projectCategories: Record<string, ProjectCategory[]>;
    resumeProject?: ResumeProject | null;
}

const props = defineProps<Props>();

// Define the form schemas for each step
const formSchemas = [
    // Step 1: Academic Level & Project Type
    z.object({
        projectType: z.enum(['undergraduate', 'postgraduate', 'hnd', 'nd']),
        projectCategoryId: z.number({ required_error: 'Please select a project category' }),
    }),

    // Step 2: University Details
    z
        .object({
            university: z.string().min(1, 'Please select your university'),
            otherUniversity: z.string().optional(),
            faculty: z.string().min(2, 'Faculty is required'),
            department: z.string().min(2, 'Department is required'),
            course: z.string().min(2, 'Course of study is required'),
        })
        .refine(
            (data) => {
                // If university is "other", otherUniversity must be provided
                if (data.university === 'other') {
                    return data.otherUniversity && data.otherUniversity.trim().length >= 2;
                }
                return true;
            },
            {
                message: "University name is required when selecting 'Other'",
                path: ['otherUniversity'],
            },
        ),

    // Step 3: Research & Supervisor Details
    z.object({
        fieldOfStudy: z.string().optional(), // Optional - AI can help guide students
        supervisorName: z.string().optional(),
        matricNumber: z.string().optional(),
        academicSession: z.string().min(1, 'Academic session is required'),
        workingMode: z.enum(['auto', 'manual']),
        aiAssistanceLevel: z.enum(['minimal', 'moderate', 'maximum']).optional(),
    }),
];

// Computed schema that reacts to step changes
const currentSchema = computed(() => formSchemas[currentStep.value - 1]);

// Check if in development mode
const isDevelopment = import.meta.env.DEV;

// ==========================================
// STEP-AWARE STATE MANAGEMENT SYSTEM
// ==========================================
const currentStep = ref(1);
const currentProjectId = ref<number | null>(null);
const isInitializing = ref(true);

// Step-aware wizard data
const wizardData = ref<WizardData>({
    format_version: '2.0',
    steps: {},
    current_step: 1,
    furthest_completed_step: 0,
});

// Selected project type for category filtering
const selectedProjectType = ref<string>('');

// Auto-save timer
let autoSaveTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * GET CURRENT STEP DATA FROM WIZARD
 */
const getCurrentStepData = (step: number): Record<string, any> => {
    const stepKey = step.toString();
    return wizardData.value.steps[stepKey]?.data ?? {};
};

/**
 * SET CURRENT STEP DATA IN WIZARD
 */
const setCurrentStepData = (step: number, data: Record<string, any>) => {
    const stepKey = step.toString();
    wizardData.value.steps[stepKey] = {
        data,
        completed: isStepComplete(step, data),
        timestamp: new Date().toISOString(),
    };

    // Update progress tracking
    wizardData.value.current_step = Math.max(wizardData.value.current_step, step);
    if (wizardData.value.steps[stepKey].completed) {
        wizardData.value.furthest_completed_step = Math.max(wizardData.value.furthest_completed_step, step);
    }
};

/**
 * CHECK IF STEP DATA IS COMPLETE
 */
const isStepComplete = (step: number, data: Record<string, any>): boolean => {
    const requiredFields = {
        1: ['projectType', 'projectCategoryId'],
        2: ['university', 'faculty', 'department', 'course'],
        3: ['academicSession', 'workingMode'], // fieldOfStudy is now optional
    };

    const required = requiredFields[step as keyof typeof requiredFields] || [];
    return required.every((field) => data[field] && data[field] !== '');
};

/**
 * GET ALL STEPS DATA COMBINED
 */
const getAllStepsData = (): Record<string, any> => {
    const allData: Record<string, any> = {};

    // Merge data from all completed steps
    Object.values(wizardData.value.steps).forEach((step) => {
        Object.assign(allData, step.data);
    });

    return allData;
};

/**
 * WATCH FOR STEP CHANGES
 */
watch(currentStep, (newStep) => {
    console.log('👀 Step changed to:', newStep);

    // Update wizard data current step
    wizardData.value.current_step = newStep;

    // Force form re-render with current step data
    nextTick(() => {
        initialFormValues.value = getCurrentStepData(newStep);
        formKey.value++;

        // Sync selectedProjectType for category filtering
        const stepData = getCurrentStepData(newStep);
        if (stepData.projectType) {
            selectedProjectType.value = stepData.projectType;
        }
    });
});

/**
 * STEP-AWARE STATE RESTORATION
 */
onMounted(() => {
    if (props.resumeProject) {
        console.log('📄 Resuming project setup...', props.resumeProject);

        // Restore wizard data and current step
        currentStep.value = props.resumeProject.current_step;
        currentProjectId.value = props.resumeProject.id;
        wizardData.value = props.resumeProject.wizard_data;

        // Initialize form with current step data
        const currentStepData = getCurrentStepData(currentStep.value);
        initialFormValues.value = currentStepData;

        // Sync selectedProjectType for category filtering
        if (currentStepData.projectType) {
            selectedProjectType.value = currentStepData.projectType;
        }

        // Force form re-render
        formKey.value = Date.now();

        console.log('✅ Step-aware state restored:', {
            step: currentStep.value,
            wizardData: wizardData.value,
            currentStepData,
            selectedProjectType: selectedProjectType.value,
        });
    } else {
        console.log('🆕 Starting fresh project setup');
        initialFormValues.value = {};
        formKey.value = Date.now();
    }

    // Mark initialization complete
    nextTick(() => {
        isInitializing.value = false;
    });
});

/**
 * AUTO-SAVE SYSTEM WITH PROPER CHANGE DETECTION
 */
const saveProgress = async (step: number, formData: Record<string, any>) => {
    try {
        console.log('💾 Saving progress - Step:', step, 'Data:', formData);

        // Don't save during initialization
        if (isInitializing.value) {
            console.log('⏸️ Skipping save during initialization');
            return;
        }

        // Get CSRF token more reliably
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (!csrfToken) {
            console.error('❌ CSRF token not found');
            toast('Error', {
                description: 'Security token missing. Please refresh the page.',
            });
            return;
        }

        const response = await fetch(route('projects.save-wizard-progress'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                project_id: currentProjectId.value,
                step: step,
                data: formData,
            }),
        });

        // Handle response
        if (response.ok) {
            const result = await response.json();
            if (result.success) {
                currentProjectId.value = result.project_id;
                lastSavedValues.value = { ...formData };
                console.log('✅ Progress saved successfully');
            } else {
                console.error('❌ Save failed:', result);
                toast('Save Failed', {
                    description: result.message || 'Failed to save progress.',
                });
            }
        } else {
            console.error('❌ Save request failed:', response.status, response.statusText);

            // Handle specific error codes
            if (response.status === 419) {
                toast('Session Expired', {
                    description: 'Please refresh the page to continue.',
                });
                return;
            } else if (response.status === 422) {
                // Validation errors
                const errorData = await response.json().catch(() => ({}));
                console.error('❌ Validation errors:', errorData);
                toast('Validation Error', {
                    description: 'Some form data is invalid.',
                });
                return;
            }

            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
    } catch (error) {
        console.error('❌ Failed to save progress:', error);

        // Show user-friendly error message
        if (error instanceof SyntaxError && error.message.includes('Unexpected token')) {
            toast('Session Error', {
                description: 'Your session may have expired. Please refresh the page.',
            });
        } else {
            toast('Auto-save Error', {
                description: 'Failed to save your progress automatically.',
            });
        }
    }
};

/**
 * IMPROVED DEBOUNCED AUTO-SAVE
 */
const debouncedSave = (step: number, data: Record<string, any>) => {
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
    }

    autoSaveTimer = setTimeout(() => {
        saveProgress(step, data);
    }, 1000); // Save 1 second after user stops typing
};

/**
 * ENHANCED STEP NAVIGATION WITH COMPLETE DATA PRESERVATION
 */
const goToStep = async (targetStep: number, currentValues: Record<string, any>) => {
    // Don't navigate to the same step
    if (targetStep === currentStep.value) return;

    console.log('📄 Navigating from step', currentStep.value, 'to step', targetStep);

    // Save current step data before navigation
    const currentStepData = getCurrentStepFields(currentValues, currentStep.value);
    if (Object.keys(currentStepData).length > 0) {
        console.log('💾 Saving step', currentStep.value, 'data before navigation:', currentStepData);

        // Update wizard data
        setCurrentStepData(currentStep.value, currentStepData);

        // Save to backend
        await saveProgress(currentStep.value, currentStepData);
    }

    // Navigate to target step (this will trigger the watcher)
    currentStep.value = targetStep;

    console.log('✅ Navigation complete - now on step:', currentStep.value);
};

/**
 * CHECK IF STEP IS ACCESSIBLE
 * Users can navigate to any previous step or the next step if current is valid
 */
const isStepAccessible = computed(() => (targetStep: number, currentStep: number, isCurrentValid: boolean) => {
    // Always allow going backward to previously visited steps
    const maxVisited = Math.max(currentStep, props.resumeProject?.setup_step ?? 1);
    if (targetStep <= maxVisited) return true;

    // Allow going forward only if current step is valid and it's the immediate next step
    if (targetStep === currentStep + 1 && isCurrentValid) return true;

    // Block skipping ahead beyond the furthest visited step
    return false;
});

const steps = [
    {
        step: 1,
        title: 'Academic Setup',
        description: 'Level & project type',
        icon: GraduationCap,
    },
    {
        step: 2,
        title: 'Institution',
        description: 'University details',
        icon: School,
    },
    {
        step: 3,
        title: 'Research Details',
        description: 'Field & supervisor info',
        icon: FileText,
    },
];

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
    { value: 'fcuotuoke', label: 'Federal College of Education (Technical), Otuoke', fullName: 'Federal College of Education (Technical), Otuoke' },
    { value: 'fuam', label: 'Federal University of Agriculture, Makurdi (FUAM)', fullName: 'Federal University of Agriculture, Makurdi' },
    { value: 'gombe', label: 'Gombe State University', fullName: 'Gombe State University' },
    { value: 'imsu', label: 'Imo State University, Owerri (IMSU)', fullName: 'Imo State University, Owerri' },
    { value: 'jabu', label: 'Joseph Ayo Babalola University, Ikeji-Arakeji', fullName: 'Joseph Ayo Babalola University, Ikeji-Arakeji' },
    { value: 'kasu', label: 'Kaduna State University (KASU)', fullName: 'Kaduna State University' },
    {
        value: 'kasu',
        label: 'Kano State University of Science and Technology, Wudil',
        fullName: 'Kano State University of Science and Technology, Wudil',
    },
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

// Nigerian University Departments (organized by common faculty groupings)
const departments = [
    // Arts & Humanities
    { value: 'english_literature', label: 'English and Literary Studies', faculty: 'arts' },
    { value: 'history_strategic_studies', label: 'History and Strategic Studies', faculty: 'arts' },
    { value: 'linguistics', label: 'Linguistics and Nigerian Languages', faculty: 'arts' },
    { value: 'philosophy', label: 'Philosophy', faculty: 'arts' },
    { value: 'religious_studies', label: 'Religious Studies', faculty: 'arts' },
    { value: 'theatre_arts', label: 'Theatre Arts', faculty: 'arts' },
    { value: 'music', label: 'Music', faculty: 'arts' },
    { value: 'fine_arts', label: 'Fine and Applied Arts', faculty: 'arts' },

    // Engineering
    { value: 'civil_engineering', label: 'Civil Engineering', faculty: 'engineering' },
    { value: 'mechanical_engineering', label: 'Mechanical Engineering', faculty: 'engineering' },
    { value: 'electrical_engineering', label: 'Electrical/Electronic Engineering', faculty: 'engineering' },
    { value: 'computer_engineering', label: 'Computer Engineering', faculty: 'engineering' },
    { value: 'chemical_engineering', label: 'Chemical Engineering', faculty: 'engineering' },
    { value: 'petroleum_engineering', label: 'Petroleum and Gas Engineering', faculty: 'engineering' },
    { value: 'agricultural_engineering', label: 'Agricultural and Bioresources Engineering', faculty: 'engineering' },
    { value: 'metallurgical_engineering', label: 'Metallurgical and Materials Engineering', faculty: 'engineering' },

    // Sciences
    { value: 'computer_science', label: 'Computer Science', faculty: 'physical_sciences' },
    { value: 'mathematics', label: 'Mathematics', faculty: 'physical_sciences' },
    { value: 'physics', label: 'Physics', faculty: 'physical_sciences' },
    { value: 'chemistry', label: 'Chemistry', faculty: 'physical_sciences' },
    { value: 'statistics', label: 'Statistics', faculty: 'physical_sciences' },
    { value: 'geology', label: 'Geology', faculty: 'physical_sciences' },
    { value: 'geography', label: 'Geography and Meteorology', faculty: 'physical_sciences' },

    // Life Sciences
    { value: 'biology', label: 'Biology', faculty: 'life_sciences' },
    { value: 'biochemistry', label: 'Biochemistry', faculty: 'life_sciences' },
    { value: 'microbiology', label: 'Microbiology', faculty: 'life_sciences' },
    { value: 'botany', label: 'Plant Science and Biotechnology', faculty: 'life_sciences' },
    { value: 'zoology', label: 'Zoology and Environmental Biology', faculty: 'life_sciences' },
    { value: 'marine_biology', label: 'Marine Biology', faculty: 'life_sciences' },

    // Social Sciences
    { value: 'economics', label: 'Economics', faculty: 'social_sciences' },
    { value: 'political_science', label: 'Political Science', faculty: 'social_sciences' },
    { value: 'sociology', label: 'Sociology and Anthropology', faculty: 'social_sciences' },
    { value: 'psychology', label: 'Psychology', faculty: 'social_sciences' },
    { value: 'mass_communication', label: 'Mass Communication', faculty: 'social_sciences' },
    { value: 'social_work', label: 'Social Work', faculty: 'social_sciences' },
    { value: 'international_relations', label: 'International Studies and Diplomacy', faculty: 'social_sciences' },

    // Management Sciences
    { value: 'accounting', label: 'Accountancy', faculty: 'management_sciences' },
    { value: 'business_administration', label: 'Business Administration', faculty: 'management_sciences' },
    { value: 'banking_finance', label: 'Banking and Finance', faculty: 'management_sciences' },
    { value: 'marketing', label: 'Marketing', faculty: 'management_sciences' },
    { value: 'public_administration', label: 'Public Administration and Local Government', faculty: 'management_sciences' },
    { value: 'insurance', label: 'Insurance', faculty: 'management_sciences' },

    // Education
    { value: 'educational_foundations', label: 'Educational Foundations', faculty: 'education' },
    { value: 'curriculum_instruction', label: 'Curriculum Studies and Educational Technology', faculty: 'education' },
    { value: 'educational_psychology', label: 'Educational Psychology', faculty: 'education' },
    { value: 'adult_education', label: 'Adult Education and Extra-Mural Studies', faculty: 'education' },
    { value: 'library_information_science', label: 'Library and Information Science', faculty: 'education' },

    // Medicine & Health Sciences
    { value: 'medicine_surgery', label: 'Medicine and Surgery', faculty: 'medicine' },
    { value: 'anatomy', label: 'Anatomy', faculty: 'basic_medical_sciences' },
    { value: 'physiology', label: 'Physiology', faculty: 'basic_medical_sciences' },
    { value: 'pharmacology', label: 'Pharmacology and Toxicology', faculty: 'basic_medical_sciences' },
    { value: 'pathology', label: 'Pathology', faculty: 'clinical_sciences' },
    { value: 'surgery', label: 'Surgery', faculty: 'clinical_sciences' },
    { value: 'internal_medicine', label: 'Internal Medicine', faculty: 'clinical_sciences' },
    { value: 'nursing_science', label: 'Nursing Science', faculty: 'nursing' },

    // Agriculture
    { value: 'agronomy', label: 'Agronomy', faculty: 'agriculture' },
    { value: 'animal_science', label: 'Animal Science and Range Management', faculty: 'agriculture' },
    { value: 'soil_science', label: 'Soil Science and Land Resources Management', faculty: 'agriculture' },
    { value: 'crop_science', label: 'Crop Science', faculty: 'agriculture' },
    { value: 'agricultural_economics', label: 'Agricultural Economics', faculty: 'agriculture' },
    { value: 'food_technology', label: 'Food Science and Technology', faculty: 'agriculture' },

    // Environmental Sciences
    { value: 'architecture', label: 'Architecture', faculty: 'environmental_sciences' },
    { value: 'urban_planning', label: 'Urban and Regional Planning', faculty: 'environmental_sciences' },
    { value: 'estate_management', label: 'Estate Management', faculty: 'environmental_sciences' },
    { value: 'building_technology', label: 'Building', faculty: 'environmental_sciences' },
    { value: 'quantity_surveying', label: 'Quantity Surveying', faculty: 'environmental_sciences' },

    // Law
    { value: 'private_law', label: 'Private and Property Law', faculty: 'law' },
    { value: 'public_law', label: 'Public and International Law', faculty: 'law' },
    { value: 'commercial_law', label: 'Commercial and Industrial Law', faculty: 'law' },
] as const;

// Watch for academic level selection to show relevant categories
const getAvailableCategories = (academicLevel: string) => {
    return props.projectCategories[academicLevel] || [];
};

// Watch for project type changes with proper handling
watch(selectedProjectType, (newValue) => {
    if (newValue && !isInitializing.value) {
        const mergedData = {
            ...lastSavedValues.value,
            projectType: newValue,
        };
        debouncedSave(currentStep.value, mergedData);
    }
});

// Track current form values and last saved values
const currentFormValues = ref<Record<string, any>>({});
const lastSavedValues = ref<Record<string, any>>({});
const initialFormValues = ref<Record<string, any>>({});
const formKey = ref(0); // Key for forcing form re-render

// Watch for form values changes to keep selectedProjectType in sync
watch(
    initialFormValues,
    (newValues) => {
        if (newValues?.projectType && newValues.projectType !== selectedProjectType.value) {
            selectedProjectType.value = newValues.projectType;
            console.log('🔄 Synced selectedProjectType from form values:', newValues.projectType);
        }
    },
    { deep: true, immediate: true },
);

/**
 * GET CURRENT STEP FIELDS - Only fields relevant to current step
 */
const getCurrentStepFields = (values: Record<string, any>, step: number): Record<string, any> => {
    const stepFieldsMap = {
        1: ['projectType', 'projectCategoryId'], // Step 1: Academic Level & Project Type
        2: ['university', 'otherUniversity', 'faculty', 'department', 'course'], // Step 2: University Details
        3: ['fieldOfStudy', 'supervisorName', 'matricNumber', 'academicSession', 'workingMode', 'aiAssistanceLevel'], // Step 3: Research Details
    };

    const relevantFields = stepFieldsMap[step as keyof typeof stepFieldsMap] || [];

    return Object.fromEntries(
        Object.entries(values || {}).filter(([key, value]) => {
            return relevantFields.includes(key) && value !== null && value !== undefined && value !== '';
        }),
    );
};

/**
 * STEP-AWARE AUTO-SAVE SYSTEM
 */
const autoSaveFormValues = (values: Record<string, any>) => {
    // Don't save during initialization
    if (isInitializing.value) {
        return '';
    }

    // Get only fields relevant to current step
    const stepData = getCurrentStepFields(values, currentStep.value);

    // Update wizard data with current step changes
    if (Object.keys(stepData).length > 0) {
        const previousStepData = getCurrentStepData(currentStep.value);
        const hasChanged = JSON.stringify(stepData) !== JSON.stringify(previousStepData);

        if (hasChanged) {
            console.log('🔄 Step', currentStep.value, 'data changed:', stepData);

            // Update wizard data
            setCurrentStepData(currentStep.value, stepData);

            // Sync selectedProjectType for category filtering
            if (stepData.projectType) {
                selectedProjectType.value = stepData.projectType;
            }

            // Auto-save to backend
            debouncedSave(currentStep.value, stepData);
        }
    }

    return ''; // Return empty string for template
};

function onSubmit(values: any) {
    console.log('🚀 Submitting final step with current values:', values);

    // First save current step data
    const currentStepData = getCurrentStepFields(values, currentStep.value);
    setCurrentStepData(currentStep.value, currentStepData);

    // Get all data from all steps
    const allStepsData = getAllStepsData();
    console.log('📋 All steps data for submission:', allStepsData);

    // Validate we have all required data
    if (!allStepsData.projectType || !allStepsData.university) {
        toast('Incomplete Data', {
            description: 'Please complete all previous steps before submitting.',
        });
        return;
    }

    // Prepare project data for final submission
    const projectData = {
        project_category_id: allStepsData.projectCategoryId,
        type: allStepsData.projectType,
        university: allStepsData.university === 'other' ? allStepsData.otherUniversity : allStepsData.university,
        faculty: allStepsData.faculty,
        department: allStepsData.department,
        course: allStepsData.course,
        field_of_study: allStepsData.fieldOfStudy,
        supervisor_name: allStepsData.supervisorName,
        matric_number: allStepsData.matricNumber,
        academic_session: allStepsData.academicSession,
        mode: allStepsData.workingMode,
        ai_assistance_level: allStepsData.aiAssistanceLevel,
    };

    console.log('🎯 Final project data for submission:', projectData);

    // Submit to backend
    router.post(route('projects.store'), projectData, {
        onSuccess: () => {
            toast('Project Created!', {
                description: 'Your project has been set up successfully.',
            });
        },
        onError: (errors) => {
            console.error('❌ Project creation failed:', errors);
            toast('Error', {
                description: 'Failed to create project. Please try again.',
            });
        },
    });
}
</script>

<template>
    <AppLayout title="Create New Project">
        <div class="mx-auto max-w-4xl space-y-6">
            <div>
                <h1 class="text-3xl font-bold">Create Your Project</h1>
                <p class="text-muted-foreground">Set up your final year project in just a few steps</p>
                <div v-if="currentStep > 1" class="mt-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs text-blue-600">
                    💡 <strong>Tip:</strong> You can click on any previous step to go back and make changes. Your progress is automatically saved!
                </div>
            </div>

            <Card>
                <CardContent class="pt-6">
                    <Form
                        :key="formKey"
                        v-slot="{ meta, values, validate }"
                        as=""
                        keep-values
                        :validation-schema="toTypedSchema(currentSchema)"
                        :initial-values="initialFormValues"
                    >
                        <!-- Auto-save values when they change -->
                        <template v-if="!isInitializing">{{ autoSaveFormValues(values) }}</template>
                        <Stepper v-model="currentStep" class="w-full">
                            <form
                                @submit="
                                    (e) => {
                                        e.preventDefault();
                                        validate();

                                        if (currentStep === steps.length && meta.valid) {
                                            onSubmit(values);
                                        }
                                    }
                                "
                                class="w-full"
                            >
                                <!-- Stepper Header with Fixed Line Positioning -->
                                <div class="flex w-full items-start justify-start">
                                    <!-- Stepper Items -->
                                    <template v-for="(step, index) in steps" :key="step.step">
                                        <StepperItem v-slot="{ state }" class="relative flex flex-shrink-0 flex-col items-center" :step="step.step">
                                            <StepperTrigger as-child>
                                                <Button
                                                    :variant="state === 'completed' || state === 'active' ? 'default' : 'outline'"
                                                    size="icon"
                                                    class="relative z-10 shrink-0 rounded-full"
                                                    :class="[
                                                        state === 'active' && 'ring-2 ring-ring ring-offset-2 ring-offset-background',
                                                        !isStepAccessible(step.step, currentStep, meta.valid) && 'cursor-not-allowed opacity-50',
                                                    ]"
                                                    :disabled="!isStepAccessible(step.step, currentStep, meta.valid)"
                                                    @click="goToStep(step.step, values)"
                                                    type="button"
                                                >
                                                    <Check v-if="state === 'completed'" class="size-5" />
                                                    <component :is="step.icon" v-else class="size-5" />
                                                </Button>
                                            </StepperTrigger>

                                            <div class="mt-5 flex flex-col items-center text-center">
                                                <StepperTitle
                                                    :class="[
                                                        state === 'active' && 'text-primary',
                                                        isStepAccessible(step.step, currentStep, meta.valid) &&
                                                            step.step !== currentStep &&
                                                            'cursor-pointer text-blue-600 hover:text-blue-800',
                                                    ]"
                                                    class="text-sm font-semibold transition lg:text-base"
                                                    @click="isStepAccessible(step.step, currentStep, meta.valid) ? goToStep(step.step, values) : null"
                                                >
                                                    {{ step.title }}
                                                </StepperTitle>
                                                <StepperDescription
                                                    :class="[
                                                        state === 'active' && 'text-primary',
                                                        isStepAccessible(step.step, currentStep, meta.valid) &&
                                                            step.step !== currentStep &&
                                                            'text-blue-500',
                                                    ]"
                                                    class="text-xs text-muted-foreground transition md:text-sm"
                                                >
                                                    {{ step.description }}
                                                    <span
                                                        v-if="isStepAccessible(step.step, currentStep, meta.valid) && step.step < currentStep"
                                                        class="mt-1 block text-xs text-blue-600"
                                                    >
                                                        (click to edit)
                                                    </span>
                                                </StepperDescription>
                                            </div>
                                        </StepperItem>

                                        <!-- Connector line between steps -->
                                        <div
                                            v-if="index < steps.length - 1"
                                            :class="[
                                                'mt-5 h-0.5 w-full max-w-[200px] transition-all duration-300',
                                                currentStep > step.step ? 'bg-primary' : 'bg-muted',
                                            ]"
                                        />
                                    </template>
                                </div>

                                <!-- Form Content -->
                                <div class="mt-8 space-y-6">
                                    <!-- Step 1: Academic Level & Project Type -->
                                    <div v-if="currentStep === 1" class="space-y-6">
                                        <FormField v-slot="{ componentField }" name="projectType">
                                            <FormItem>
                                                <FormLabel>What is your academic level?</FormLabel>
                                                <FormControl>
                                                    <RadioGroup
                                                        :model-value="componentField.modelValue"
                                                        @update:model-value="
                                                            (value: string) => {
                                                                componentField['onUpdate:modelValue']?.(value);
                                                                selectedProjectType = value;
                                                            }
                                                        "
                                                    >
                                                        <div class="grid grid-cols-2 gap-4">
                                                            <label
                                                                class="flex cursor-pointer items-center space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent"
                                                            >
                                                                <RadioGroupItem value="undergraduate" />
                                                                <div class="space-y-1">
                                                                    <p class="text-sm font-medium">Undergraduate</p>
                                                                    <p class="text-xs text-muted-foreground">BSc/BTech final year project</p>
                                                                </div>
                                                            </label>
                                                            <label
                                                                class="flex cursor-pointer items-center space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent"
                                                            >
                                                                <RadioGroupItem value="postgraduate" />
                                                                <div class="space-y-1">
                                                                    <p class="text-sm font-medium">Postgraduate</p>
                                                                    <p class="text-xs text-muted-foreground">MSc/PhD thesis</p>
                                                                </div>
                                                            </label>
                                                            <label
                                                                class="flex cursor-pointer items-center space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent"
                                                            >
                                                                <RadioGroupItem value="hnd" />
                                                                <div class="space-y-1">
                                                                    <p class="text-sm font-medium">HND</p>
                                                                    <p class="text-xs text-muted-foreground">Higher National Diploma</p>
                                                                </div>
                                                            </label>
                                                            <label
                                                                class="flex cursor-pointer items-center space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent"
                                                            >
                                                                <RadioGroupItem value="nd" />
                                                                <div class="space-y-1">
                                                                    <p class="text-sm font-medium">ND</p>
                                                                    <p class="text-xs text-muted-foreground">National Diploma</p>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </RadioGroup>
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <!-- Project Category -->
                                        <FormField v-slot="{ componentField }" name="projectCategoryId">
                                            <FormItem>
                                                <FormLabel>What type of project are you creating?</FormLabel>
                                                <FormControl>
                                                    <RadioGroup
                                                        :model-value="componentField.modelValue?.toString()"
                                                        @update:model-value="
                                                            (value: string) => componentField['onUpdate:modelValue']?.(parseInt(value))
                                                        "
                                                    >
                                                        <div class="space-y-3">
                                                            <template
                                                                v-for="category in getAvailableCategories(selectedProjectType)"
                                                                :key="category.id"
                                                            >
                                                                <label
                                                                    class="flex cursor-pointer items-start space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent"
                                                                >
                                                                    <RadioGroupItem :value="category.id.toString()" class="mt-1" />
                                                                    <div class="flex-1 space-y-2">
                                                                        <div>
                                                                            <p class="font-medium">{{ category.name }}</p>
                                                                            <p class="text-sm text-muted-foreground">{{ category.description }}</p>
                                                                        </div>
                                                                        <div class="grid grid-cols-3 gap-2 text-xs text-muted-foreground">
                                                                            <div class="flex items-center gap-1">
                                                                                <BookOpen class="h-3 w-3" />
                                                                                {{ category.default_chapter_count }}
                                                                                chapters
                                                                            </div>
                                                                            <div class="flex items-center gap-1">
                                                                                <FileText class="h-3 w-3" />
                                                                                ~{{ (category.target_word_count / 1000).toFixed(0) }}k words
                                                                            </div>
                                                                            <div class="flex items-center gap-1">
                                                                                <GraduationCap class="h-3 w-3" />
                                                                                {{ category.target_duration }}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </label>
                                                            </template>
                                                            <div v-if="!selectedProjectType" class="py-8 text-center text-muted-foreground">
                                                                Please select an academic level first
                                                            </div>
                                                            <div
                                                                v-else-if="getAvailableCategories(selectedProjectType).length === 0"
                                                                class="py-8 text-center text-muted-foreground"
                                                            >
                                                                No project categories available for this academic level
                                                            </div>
                                                        </div>
                                                    </RadioGroup>
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>
                                    </div>

                                    <!-- Step 2: University Details -->
                                    <div v-if="currentStep === 2" class="space-y-4">
                                        <FormField v-slot="{ componentField }" name="university">
                                            <FormItem class="flex flex-col">
                                                <FormLabel>University</FormLabel>
                                                <Popover>
                                                    <PopoverTrigger as-child>
                                                        <FormControl>
                                                            <Button
                                                                variant="outline"
                                                                role="combobox"
                                                                :class="
                                                                    cn(
                                                                        'w-full justify-between',
                                                                        !componentField.modelValue && 'text-muted-foreground',
                                                                    )
                                                                "
                                                            >
                                                                {{
                                                                    componentField.modelValue
                                                                        ? universities.find(
                                                                              (university) => university.value === componentField.modelValue,
                                                                          )?.label
                                                                        : 'Select your university...'
                                                                }}
                                                                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
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
                                                                        :value="university.label"
                                                                        @select="
                                                                            () => {
                                                                                componentField['onUpdate:modelValue']?.(university.value);
                                                                            }
                                                                        "
                                                                    >
                                                                        {{ university.label }}
                                                                        <Check
                                                                            :class="
                                                                                cn(
                                                                                    'ml-auto h-4 w-4',
                                                                                    university.value === componentField.modelValue
                                                                                        ? 'opacity-100'
                                                                                        : 'opacity-0',
                                                                                )
                                                                            "
                                                                        />
                                                                    </CommandItem>
                                                                </CommandGroup>
                                                            </CommandList>
                                                        </Command>
                                                    </PopoverContent>
                                                </Popover>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <!-- Other University Input -->
                                        <FormField v-slot="{ componentField }" name="otherUniversity" v-if="values.university === 'other'">
                                            <FormItem>
                                                <FormLabel>University Name</FormLabel>
                                                <FormControl>
                                                    <Input type="text" placeholder="Enter your university name" v-bind="componentField" />
                                                </FormControl>
                                                <FormDescription> Please enter the full name of your university </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <div class="grid grid-cols-2 gap-4">
                                            <FormField v-slot="{ componentField }" name="faculty">
                                                <FormItem class="flex flex-col">
                                                    <FormLabel>Faculty</FormLabel>
                                                    <Popover>
                                                        <PopoverTrigger as-child>
                                                            <FormControl>
                                                                <Button
                                                                    variant="outline"
                                                                    role="combobox"
                                                                    :class="
                                                                        cn(
                                                                            'w-full justify-between',
                                                                            !componentField.modelValue && 'text-muted-foreground',
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        componentField.modelValue
                                                                            ? faculties.find((faculty) => faculty.value === componentField.modelValue)
                                                                                  ?.label
                                                                            : 'Select faculty...'
                                                                    }}
                                                                    <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                                </Button>
                                                            </FormControl>
                                                        </PopoverTrigger>
                                                        <PopoverContent class="w-[300px] p-0">
                                                            <Command>
                                                                <CommandInput placeholder="Search faculty..." />
                                                                <CommandEmpty>No faculty found.</CommandEmpty>
                                                                <CommandList>
                                                                    <CommandGroup>
                                                                        <CommandItem
                                                                            v-for="faculty in faculties"
                                                                            :key="faculty.value"
                                                                            :value="faculty.value"
                                                                            @select="
                                                                                () => {
                                                                                    componentField['onUpdate:modelValue']?.(faculty.value);
                                                                                }
                                                                            "
                                                                        >
                                                                            <Check
                                                                                :class="
                                                                                    cn(
                                                                                        'mr-2 h-4 w-4',
                                                                                        componentField.modelValue === faculty.value
                                                                                            ? 'opacity-100'
                                                                                            : 'opacity-0',
                                                                                    )
                                                                                "
                                                                            />
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

                                            <FormField v-slot="{ componentField }" name="department">
                                                <FormItem class="flex flex-col">
                                                    <FormLabel>Department</FormLabel>
                                                    <Popover>
                                                        <PopoverTrigger as-child>
                                                            <FormControl>
                                                                <Button
                                                                    variant="outline"
                                                                    role="combobox"
                                                                    :class="
                                                                        cn(
                                                                            'w-full justify-between',
                                                                            !componentField.modelValue && 'text-muted-foreground',
                                                                        )
                                                                    "
                                                                >
                                                                    {{
                                                                        componentField.modelValue
                                                                            ? departments.find((dept) => dept.value === componentField.modelValue)
                                                                                  ?.label
                                                                            : 'Select department...'
                                                                    }}
                                                                    <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                                </Button>
                                                            </FormControl>
                                                        </PopoverTrigger>
                                                        <PopoverContent class="w-[350px] p-0">
                                                            <Command>
                                                                <CommandInput placeholder="Search department..." />
                                                                <CommandEmpty>No department found.</CommandEmpty>
                                                                <CommandList>
                                                                    <CommandGroup>
                                                                        <CommandItem
                                                                            v-for="dept in departments"
                                                                            :key="dept.value"
                                                                            :value="dept.value"
                                                                            @select="
                                                                                () => {
                                                                                    componentField['onUpdate:modelValue']?.(dept.value);
                                                                                }
                                                                            "
                                                                        >
                                                                            <Check
                                                                                :class="
                                                                                    cn(
                                                                                        'mr-2 h-4 w-4',
                                                                                        componentField.modelValue === dept.value
                                                                                            ? 'opacity-100'
                                                                                            : 'opacity-0',
                                                                                    )
                                                                                "
                                                                            />
                                                                            {{ dept.label }}
                                                                        </CommandItem>
                                                                    </CommandGroup>
                                                                </CommandList>
                                                            </Command>
                                                        </PopoverContent>
                                                    </Popover>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>
                                        </div>

                                        <FormField v-slot="{ componentField }" name="course">
                                            <FormItem>
                                                <FormLabel>Course of Study</FormLabel>
                                                <FormControl>
                                                    <Input type="text" placeholder="e.g., Computer Science" v-bind="componentField" />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>
                                    </div>

                                    <!-- Step 3: Research & Working Details -->
                                    <div v-if="currentStep === 3" class="space-y-6">
                                        <FormField v-slot="{ componentField }" name="fieldOfStudy">
                                            <FormItem>
                                                <FormLabel>Field/Area of Research (Optional)</FormLabel>
                                                <FormControl>
                                                    <Input
                                                        type="text"
                                                        placeholder="e.g., Artificial Intelligence, Web Development (leave blank if unsure)"
                                                        v-bind="componentField"
                                                    />
                                                </FormControl>
                                                <FormDescription>
                                                    Your specific area of focus for this project. Leave blank if you need AI guidance in choosing a
                                                    research area.
                                                </FormDescription>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <FormField v-slot="{ componentField }" name="supervisorName">
                                            <FormItem>
                                                <FormLabel>Supervisor Name (Optional)</FormLabel>
                                                <FormControl>
                                                    <Input type="text" placeholder="e.g., Dr. John Doe" v-bind="componentField" />
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>

                                        <div class="grid grid-cols-2 gap-4">
                                            <FormField v-slot="{ componentField }" name="matricNumber">
                                                <FormItem>
                                                    <FormLabel>Matric Number (Optional)</FormLabel>
                                                    <FormControl>
                                                        <Input type="text" placeholder="e.g., 2019/1234" v-bind="componentField" />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>

                                            <FormField v-slot="{ componentField }" name="academicSession">
                                                <FormItem>
                                                    <FormLabel>Academic Session</FormLabel>
                                                    <FormControl>
                                                        <Input type="text" placeholder="e.g., 2024/2025" v-bind="componentField" />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>
                                        </div>

                                        <!-- Working Mode -->
                                        <FormField v-slot="{ componentField }" name="workingMode">
                                            <FormItem>
                                                <FormLabel>How would you like to work on your project?</FormLabel>
                                                <FormControl>
                                                    <RadioGroup v-bind="componentField">
                                                        <div class="space-y-3">
                                                            <label
                                                                class="flex cursor-pointer items-start space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent"
                                                            >
                                                                <RadioGroupItem value="auto" class="mt-1" />
                                                                <div class="space-y-1">
                                                                    <p class="font-medium">Auto Mode</p>
                                                                    <p class="text-sm text-muted-foreground">
                                                                        AI generates complete chapters. You review and approve each section. Perfect
                                                                        for quick completion.
                                                                    </p>
                                                                </div>
                                                            </label>
                                                            <label
                                                                class="flex cursor-pointer items-start space-y-0 space-x-3 rounded-md border p-4 hover:bg-accent"
                                                            >
                                                                <RadioGroupItem value="manual" class="mt-1" />
                                                                <div class="space-y-1">
                                                                    <p class="font-medium">Manual Mode</p>
                                                                    <p class="text-sm text-muted-foreground">
                                                                        Co-write with AI assistance. Get suggestions as you type, maintain full
                                                                        control.
                                                                    </p>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    </RadioGroup>
                                                </FormControl>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>
                                    </div>
                                </div>

                                <!-- Navigation Buttons -->
                                <div class="mt-8 flex justify-between">
                                    <Button :disabled="currentStep <= 1" variant="outline" @click="goToStep(currentStep - 1, values)" type="button">
                                        Back
                                    </Button>

                                    <div class="flex gap-3">
                                        <Button
                                            v-if="currentStep !== steps.length"
                                            :disabled="!meta.valid"
                                            @click="goToStep(currentStep + 1, values)"
                                            type="button"
                                        >
                                            Next
                                        </Button>

                                        <Button v-if="currentStep === steps.length" type="submit" :disabled="!meta.valid"> Complete Setup </Button>
                                    </div>
                                </div>
                            </form>
                        </Stepper>
                    </Form>
                </CardContent>
            </Card>
        </div>

        <!-- Debug Panel (Development Only) -->
        <WizardDebugPanel
            v-if="isDevelopment"
            :current-step="currentStep"
            :project-id="currentProjectId"
            :form-values="currentFormValues"
            :saved-values="lastSavedValues"
            :is-initializing="isInitializing"
            @force-save="() => saveProgress(currentStep, currentFormValues)"
            @reset-form="() => {}"
        />
    </AppLayout>
</template>
