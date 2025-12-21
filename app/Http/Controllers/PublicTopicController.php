<?php

namespace App\Http\Controllers;

use App\Models\ProjectTopic;
use App\Models\Faculty;
use App\Models\GuestTopicDownload;
use App\Models\Project;
use App\Models\User;
use App\Actions\Topics\ExportTopicPdfAction;
use App\Http\Controllers\Concerns\TopicTextHelpers;
use App\Services\Topics\TopicLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class PublicTopicController extends Controller
{
    use TopicTextHelpers;

    public function index(TopicLibraryService $topicService): Response
    {
        // Fetch all topics (limit to reasonable amount for initial load, or all if feasible)
        // TopicLibraryService::getAllTopics returns a collection.
        // We'll fetch a decent number, the frontend handles client-side search/filter for now
        // as per the existing TopicsIndex.vue pattern.
        $topics = $topicService->getAllTopics(300);

        $faculties = Faculty::select(['id', 'name', 'slug'])->get();

        return Inertia::render('ProjectTopics', [
            'allTopics' => $topics,
            'faculties' => $faculties,
            'meta' => [
                'totalTopics' => $topicService->countAllTopics(),
            ],
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'topic_id' => ['required', 'integer', 'exists:project_topics,id'],
        ]);

        $topic = ProjectTopic::query()
            ->select(['id', 'title', 'description'])
            ->findOrFail($data['topic_id']);

        $request->session()->put('project_topic_prefill', [
            'id' => $topic->id,
            'title' => $topic->title,
            'description' => $topic->description,
        ]);

        if (Auth::check()) {
            return redirect()->route('projects.create');
        }

        $request->session()->put('url.intended', route('projects.create'));

        return redirect()->route('register');
    }

    public function downloadProposal(Request $request, ExportTopicPdfAction $exportAction)
    {
        $data = $request->validate([
            'topic_id' => ['required', 'integer', 'exists:project_topics,id'],
            'student_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'university' => ['required', 'string', 'max:255'],
            'faculty' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'course' => ['required', 'string', 'max:255'],
            'matric_no' => ['nullable', 'string', 'max:50'],
            'academic_level' => ['required', 'string', 'max:50'],
        ]);

        $topic = ProjectTopic::findOrFail($data['topic_id']);

        // Save guest download info
        GuestTopicDownload::create([
            'project_topic_id' => $topic->id,
            'student_name' => $data['student_name'],
            'email' => $data['email'],
            'university' => $data['university'],
            'faculty' => $data['faculty'],
            'department' => $data['department'],
            'course' => $data['course'],
            'matric_no' => $data['matric_no'],
            'academic_level' => $data['academic_level'],
            'ip_address' => $request->ip(),
        ]);

        // Create a mock project to reuse the existing PDF action
        // We'll use a temporary project that isn't saved to the DB
        $project = new Project();
        $project->id = 0; // Dummy ID
        $project->slug = 'guest-proposal-' . time();
        
        // Use clean title and converted description
        // For titles, we strip tags but allow basic formatting, then specifically remove any wrapping P tags
        $cleanTitle = str_replace(['**', '##'], '', $topic->title);
        $cleanTitle = preg_replace('/^<p>(.*)<\/p>$/i', '$1', trim($cleanTitle)); // Remove wrapping P tags
        
        $project->topic = $cleanTitle;
        $project->title = $cleanTitle;
        $project->description = $this->convertMarkdownToHtml($this->cleanTopicDescription($topic->description));
        
        $project->student_name = $data['student_name'];
        $project->type = $data['academic_level'];
        $project->course = $data['course'];
        $project->user_id = 0; // Dummy or null

        // Mock relationships with safe fallbacks
        $project->setRelation('universityRelation', (object)['name' => $data['university'] ?? 'N/A']);
        $project->setRelation('facultyRelation', (object)['name' => $data['faculty'] ?? 'N/A']);
        $project->setRelation('departmentRelation', (object)['name' => $data['department'] ?? 'N/A']);
        
        $user = new User();
        $user->name = $data['student_name'];
        $user->matric_no = $data['matric_no'];
        $project->setRelation('user', $user);

        // We need to bypass the ownership check in ExportTopicPdfAction
        // Since we are mocking the project, we'll manually call the generation logic 
        // OR we can slightly modify ExportTopicPdfAction to allow guest mode.
        
        // For now, let's see if we can just use the action directly if we set auth id matching
        // But auth()->id() might be null.
        
        // Let's actually create a dedicated helper or just copy the logic for simplicity 
        // to avoid breaking existing auth checks in the shared action.
        
        return $exportAction->execute($project, true); // We'll add a second param for guest mode
    }
}
