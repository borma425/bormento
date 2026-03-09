<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;

class AISettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'AI Personality';
    protected static ?string $title = 'AI Configuration';
    protected static ?string $navigationGroup = 'Settings';
    
    protected static string $view = 'filament.pages.a-i-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        
        if ($tenant) {
            $this->form->fill([
                'openai_api_key' => $tenant->openai_api_key,
                'ai_config' => $tenant->ai_config ?? [],
            ]);
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('OpenRouter Connection')
                    ->description('Provide your OpenRouter API key for LLM models. If left blank, the global system key will be used.')
                    ->schema([
                        TextInput::make('openai_api_key')
                            ->label('OpenRouter API Key')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ]),

                Section::make('Global Business Logic')
                    ->description('Define who the AI is and the general rules it should follow across all conversations.')
                    ->schema([
                        Textarea::make('ai_config.global_persona')
                            ->label('Global Persona (Who is the AI?)')
                            ->placeholder('e.g., You are a highly professional sales assistant for a luxury clothing brand. Your goal is to be polite and quick.')
                            ->rows(3),
                            
                        Textarea::make('ai_config.sales_guidelines')
                            ->label('Sales & Business Guidelines')
                            ->placeholder('e.g., Never offer discounts. Always recommend matching items. Apologize if an item is out of stock.')
                            ->rows(4),
                    ]),

                Section::make('Agent-Specific Instructions')
                    ->description('Provide specific behavioral instructions for each specialized agent.')
                    ->schema([
                        Textarea::make('ai_config.greeting_agent_prompt')
                            ->label('Greeting Agent Instructions')
                            ->placeholder('e.g., Welcome the user warmly and ask if they are looking for anything specific today.')
                            ->rows(3),
                            
                        Textarea::make('ai_config.catalog_agent_prompt')
                            ->label('Catalog & RAG Agent Instructions')
                            ->placeholder('e.g., When answering product questions, mention that materials are 100% Egyptian cotton.')
                            ->rows(3),
                            
                        Textarea::make('ai_config.ordering_agent_prompt')
                            ->label('Ordering Agent Instructions')
                            ->placeholder('e.g., After taking the address, always confirm that delivery takes 2-3 working days.')
                            ->rows(3),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            $data = $this->form->getState();

            $tenant->update([
                'openai_api_key' => $data['openai_api_key'],
                'ai_config' => $data['ai_config'],
            ]);

            Notification::make()
                ->title('AI Settings Saved')
                ->success()
                ->send();
        }
    }
}
