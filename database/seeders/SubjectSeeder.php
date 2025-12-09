<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $categories = [
        //     'Creative Arts' => [
        //         'Drawing & Illustration',
        //         'Painting',
        //         'Photography',
        //         'Videography',
        //         'Graphic Design',
        //         'Music Production',
        //         'Singing',
        //         'Dancing (All Styles)',
        //         'Acting / Improv',
        //         'Creative Writing',
        //     ],
            
        //     'Performing Skills' => [
        //         'Public Speaking',
        //         'Debating',
        //         'Speech Writing',
        //         'Comedy / Improv',
        //         'Stage Performance',
        //     ],
            
        //     'K-12 Subjects' => [
        //         'Math',
        //         'English',
        //         'Science',
        //         'History',
        //         'Reading',
        //         'Writing & Essays',
        //         'Test Prep',
        //     ],
            
        //     'College Success' => [
        //         'Study Skills',
        //         'Time Management',
        //         'Research Papers',
        //         'College Applications',
        //     ],
            
        //     'Language Learning' => [
        //         'Spanish',
        //         'Somali',
        //         'Arabic',
        //         'French',
        //         'Chinese',
        //     ],
            
        //     'Sports Skills' => [
        //         'Basketball',
        //         'Football (WR, QB, OL, DB)',
        //         'Soccer',
        //         'Baseball',
        //         'Track & Field',
        //         'Wrestling',
        //         'Volleyball',
        //         'Tennis',
        //         'Golf',
        //         'Swimming',
        //         'Pickleball',
        //     ],
            
        //     'Fitness & Body Training' => [
        //         'Weightlifting',
        //         'Calisthenics',
        //         'Speed & Agility',
        //         'Mobility Training',
        //         'Conditioning Programs',
        //         'Personal Training',
        //     ],
            
        //     'Tech Skills' => [
        //         'Coding - Python',
        //         'Coding - JavaScript',
        //         'Coding - C++',
        //         'Web Design',
        //         'App Building',
        //         'AI Tools & Prompting',
        //         'Cybersecurity Basics',
        //         'Data Analysis',
        //         'Gaming Development',
        //     ],
            
        //     'Digital Media' => [
        //         'Video Editing',
        //         'TikTok/YouTube Skills',
        //         'Social Media Content Creation',
        //         'Streaming (Twitch/YouTube Live)',
        //         'Photoshop / Canva Mastery',
        //     ],
            
        //     'Everyday Skills' => [
        //         'Cooking',
        //         'Cleaning Systems',
        //         'Car Basics',
        //         'Home Repair',
        //         'Budgeting',
        //         'Credit Building',
        //         'Resume & Job Skills',
        //         'Organization',
        //         'Study Routines',
        //         'Drivers Education',
        //     ],
            
        //     'Modern Adulting' => [
        //         'Taxes 101',
        //         'Getting an Apartment',
        //         'Finding a Career Path',
        //         'Communication Skills',
        //         'Conflict Resolution',
        //         'Stress Management',
        //     ],
            
        //     'Business Skills' => [
        //         'Entrepreneurship',
        //         'Sales Training',
        //         'Marketing',
        //         'Branding',
        //         'E-Commerce',
        //         'Dropshipping',
        //         'Consulting',
        //         'Real Estate Basics',
        //     ],
            
        //     'Career Development' => [
        //         'Resume Building',
        //         'Interview Coaching',
        //         'Professional Communication',
        //         'Workplace Skills',
        //         'Leadership Training',
        //     ],
            
        //     'Content Creation' => [
        //         'Viral TikTok Strategy',
        //         'YouTube Channel Growth',
        //         'Editing For Viral Clips',
        //         'Thumbnail Creation',
        //         'Script Writing',
        //     ],
            
        //     'Creator Branding' => [
        //         'Personal Brand Building',
        //         'Monetization',
        //         'Sponsorships',
        //         'Short Form Content Strategy',
        //         'Community Building',
        //     ],
            
        //     'Hobbies & Unique Skills' => [
        //         'Chess',
        //         'Magic Tricks',
        //         'Cooking Specialties',
        //         'Nail Art',
        //         'Makeup Skills',
        //         'Barbering & Fades',
        //         'Fashion Styling',
        //         'Gardening',
        //         'Sewing',
        //         'Pet Training',
        //     ],
            
        //     'Mindfulness & Mental Wellness' => [
        //         'Meditation',
        //         'Mindset Coaching',
        //         'Anxiety Management',
        //         'Productivity Coaching',
        //         'Journaling for Growth',
        //         'Confidence Building',
        //     ],
        // ];

        // foreach ($categories as $categoryName => $subjects) {
        //     // Create parent category
        //     $parent = Subject::create([
        //         'name' => $categoryName,
        //         'parent_id' => null,
        //     ]);

        //     // Create child subjects
        //     foreach ($subjects as $subjectName) {
        //         Subject::create([
        //             'name' => $subjectName,
        //             'parent_id' => $parent->id,
        //         ]);
        //     }
        // }

        $subjects = [
            'Creative Arts',
            'Academic and School Success',
            'Sports and Physical Training',
            'Tech and Future Skills',
            'Life Skills',
            'Business & Careers',
            'Creator and Influencer Skills',
            'Hobbies and Fun',
        ];

        foreach ($subjects as $subjectName) {
            Subject::create([
                'name' => $subjectName,
                'parent_id' => null,
            ]);
        }


    }
}
