<?php

namespace App\Http\Controllers;

use App\Models\Signalement;
use App\Models\HistoSignalement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class SignalementController extends Controller
{

    public function __construct()
    {
    }

    #[OA\Get(
        path: '/api/signalements',
        operationId: 'getSignalements',
        summary: 'Récupérer tous les signalements',
        description: 'Retourne la liste de tous les signalements avec pagination',
        security: [['bearerAuth' => []]],
        tags: ['Signalements'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Numéro de page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Nombre d\'éléments par page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 15)
            ),
            new OA\Parameter(
                name: 'statut',
                description: 'Filtrer par statut (1=nouveau, 2=en_cours, 3=termine)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Rechercher dans le titre ou la description',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des signalements',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Signalement')
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'last_page', type: 'integer'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function index(Request $request)
    {
        $query = Signalement::with(['utilisateur', 'historiques'])
            ->orderBy('date_creation', 'desc');

        // Filtre par statut
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        // Recherche
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhere('entreprise_responsable', 'like', '%' . $search . '%');
            });
        }

        $perPage = $request->get('per_page', 15);
        $signalements = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $signalements->items(),
            'meta' => [
                'current_page' => $signalements->currentPage(),
                'total' => $signalements->total(),
                'per_page' => $signalements->perPage(),
                'last_page' => $signalements->lastPage(),
            ]
        ]);
    }

    #[OA\Get(
        path: '/api/signalements/{id}',
        operationId: 'getSignalement',
        summary: 'Récupérer un signalement spécifique',
        description: 'Retourne les détails d\'un signalement avec son historique',
        security: [['bearerAuth' => []]],
        tags: ['Signalements'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID du signalement',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détails du signalement',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/SignalementDetail')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Signalement non trouvé',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function show($id)
    {
        $signalement = Signalement::with(['utilisateur', 'historiques'])
            ->find($id);

        if (!$signalement) {
            return response()->json([
                'success' => false,
                'message' => 'Signalement non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $signalement
        ]);
    }

    #[OA\Post(
        path: '/api/signalements',
        operationId: 'createSignalement',
        summary: 'Créer un nouveau signalement',
        description: 'Crée un nouveau signalement et l\'historique associé',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Données du signalement',
            content: new OA\JsonContent(ref: '#/components/schemas/CreateSignalementRequest')
        ),
        tags: ['Signalements'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Signalement créé avec succès',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Signalement')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Erreur de validation',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titre' => 'required|string|max:200',
            'description' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'statut' => 'nullable|integer|in:1,2,3',
            'surface_m2' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            'avancement' => 'nullable|numeric|between:0,100',
            'entreprise_responsable' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $signalement = Signalement::create([
                'titre' => $request->titre,
                'description' => $request->description,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'statut' => $request->statut ?? 1,
                'surface_m2' => $request->surface_m2,
                'budget' => $request->budget,
                'avancement' => $request->avancement ?? 0,
                'entreprise_responsable' => $request->entreprise_responsable,
                'utilisateur_id' => auth()->id(),
                'date_creation' => now(),
            ]);

            // Créer l'historique
            HistoSignalement::create([
                'signalement_id' => $signalement->id,
                'statut' => $signalement->statut,
                'date_chargement' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Signalement créé avec succès',
                'data' => $signalement->load(['utilisateur', 'historiques'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur création signalement: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du signalement'
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/signalements/{id}',
        operationId: 'updateSignalement',
        summary: 'Mettre à jour un signalement',
        description: 'Met à jour un signalement et crée un nouvel historique si le statut change',
        security: [['bearerAuth' => []]],
        tags: ['Signalements'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID du signalement',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Données de mise à jour',
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateSignalementRequest')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Signalement mis à jour',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Signalement')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Signalement non trouvé',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 422,
                description: 'Erreur de validation',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function update(Request $request, $id)
    {
        $signalement = Signalement::find($id);

        if (!$signalement) {
            return response()->json([
                'success' => false,
                'message' => 'Signalement non trouvé'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'titre' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'statut' => 'sometimes|integer|in:1,2,3',
            'surface_m2' => 'nullable|numeric|min:0',
            'budget' => 'nullable|numeric|min:0',
            'avancement' => 'nullable|numeric|between:0,100',
            'entreprise_responsable' => 'nullable|string|max:255',
            'synchronise_firebase' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $oldStatut = $signalement->statut;
            
            $signalement->update([
                'titre' => $request->titre ?? $signalement->titre,
                'description' => $request->description ?? $signalement->description,
                'statut' => $request->statut ?? $signalement->statut,
                'surface_m2' => $request->surface_m2 ?? $signalement->surface_m2,
                'budget' => $request->budget ?? $signalement->budget,
                'avancement' => $request->avancement ?? $signalement->avancement,
                'entreprise_responsable' => $request->entreprise_responsable ?? $signalement->entreprise_responsable,
                'date_modification' => now(),
                'synchronise_firebase' => $request->has('synchronise_firebase') 
                    ? $request->synchronise_firebase 
                    : $signalement->synchronise_firebase,
            ]);

            // Créer un nouvel historique si le statut a changé
            if ($request->has('statut') && $request->statut != $oldStatut) {
                HistoSignalement::create([
                    'signalement_id' => $signalement->id,
                    'statut' => $signalement->statut,
                    'date_chargement' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Signalement mis à jour avec succès',
                'data' => $signalement->load(['utilisateur', 'historiques'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur mise à jour signalement: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du signalement'
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/signalements/statistiques',
        operationId: 'getStatistiques',
        summary: 'Récupérer les statistiques des signalements',
        description: 'Retourne les statistiques globales (nombre, surface, budget, avancement)',
        security: [['bearerAuth' => []]],
        tags: ['Signalements', 'Statistiques'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques récupérées',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'total_signalements', type: 'integer'),
                                new OA\Property(property: 'par_statut', type: 'object'),
                                new OA\Property(property: 'total_surface', type: 'number', format: 'float'),
                                new OA\Property(property: 'total_budget', type: 'number', format: 'float'),
                                new OA\Property(property: 'moyenne_avancement', type: 'number', format: 'float'),
                                new OA\Property(property: 'budget_utilise', type: 'number', format: 'float'),
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Non authentifié',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function statistiques()
    {
        // Nombre total de signalements
        $totalSignalements = Signalement::count();

        // Nombre par statut
        $parStatut = Signalement::select('statut', DB::raw('COUNT(*) as count'))
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->toArray();

        // Totaux surface et budget
        $totaux = Signalement::select(
            DB::raw('COALESCE(SUM(surface_m2), 0) as total_surface'),
            DB::raw('COALESCE(SUM(budget), 0) as total_budget')
        )->first();

        // Moyenne d'avancement
        $moyenneAvancement = Signalement::whereNotNull('avancement')
            ->avg('avancement') ?? 0;

        // Budget utilisé (estimation basée sur l'avancement)
        $budgetUtilise = Signalement::select(
            DB::raw('SUM(budget * avancement / 100) as budget_utilise')
        )->first()->budget_utilise ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_signalements' => $totalSignalements,
                'par_statut' => [
                    'nouveau' => $parStatut[1] ?? 0,
                    'en_cours' => $parStatut[2] ?? 0,
                    'termine' => $parStatut[3] ?? 0,
                ],
                'total_surface' => (float) $totaux->total_surface,
                'total_budget' => (float) $totaux->total_budget,
                'moyenne_avancement' => round((float) $moyenneAvancement, 2),
                'budget_utilise' => round((float) $budgetUtilise, 2),
            ]
        ]);
    }

    #[OA\Get(
        path: '/api/signalements/statistiques/avancement-par-entreprise',
        operationId: 'getAvancementParEntreprise',
        summary: 'Récupérer l\'avancement par entreprise',
        description: 'Retourne les statistiques d\'avancement groupées par entreprise responsable',
        security: [['bearerAuth' => []]],
        tags: ['Signalements', 'Statistiques'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Statistiques par entreprise',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'entreprise', type: 'string'),
                                    new OA\Property(property: 'total_signalements', type: 'integer'),
                                    new OA\Property(property: 'moyenne_avancement', type: 'number'),
                                    new OA\Property(property: 'total_budget', type: 'number'),
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function statistiquesParEntreprise()
    {
        $statistiques = Signalement::select(
            'entreprise_responsable',
            DB::raw('COUNT(*) as total_signalements'),
            DB::raw('AVG(avancement) as moyenne_avancement'),
            DB::raw('SUM(budget) as total_budget')
        )
        ->whereNotNull('entreprise_responsable')
        ->groupBy('entreprise_responsable')
        ->orderBy('total_signalements', 'desc')
        ->get()
        ->map(function ($item) {
            return [
                'entreprise' => $item->entreprise_responsable,
                'total_signalements' => $item->total_signalements,
                'moyenne_avancement' => round($item->moyenne_avancement ?? 0, 2),
                'total_budget' => round($item->total_budget ?? 0, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $statistiques
        ]);
    }
}