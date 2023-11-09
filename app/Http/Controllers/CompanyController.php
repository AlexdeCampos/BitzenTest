<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->limit ?? 10;
        $page = $request->page ?? 1;
        $offset = (int) $limit * ((int) $page - 1);

        $companyQuery = DB::table('companies');
        $totalItems = $companyQuery->count();

        if ($request->search) {
            $search = "%$request->search%";
            $companyQuery->where('social_name', 'like', $search)
                ->orWhere('legal_name', 'like', $search);
        }

        $companyQuery->limit($limit);
        $companyQuery->offset($offset);



        $searchResult = $companyQuery->get(
            [
                "document_number",
                "social_name",
                "legal_name",
                "creation_date",
                "responsible_email",
                "responsible_name",

            ]
        );
        return $this->buildPagination($page, $limit, $offset, $totalItems, $searchResult);
    }

    public function show(string $id)
    {
        $company = Company::where('id', $id)->first();
        if (!$company) {
            return response()->json([
                'errors' => true,
                'message' => 'Empresa não encontrada.',
            ], 404);
        }
        return $company;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required|unique:companies',
            'social_name' => 'required',
            'legal_name' => 'required',
            'creation_date' => 'required|date',
            'responsible_email' => 'required|email',
            'responsible_name' => 'required',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'errors' => true,
                'message' => 'Dados incompletos ou errados, por favor verifique',
            ], 422);
        }

        $validated = $validator->validated();
        $companyData = $this->getCompanyData($validated['document_number']);
        if ($companyData["type"] && $companyData["type"] == "not_found") {
            return response()->json([
                'errors' => true,
                'message' => 'Não foi possível validar os dados da empresa, verifique se o CNPJ enviado está correto',
            ], 404);
        }

        $hasValidCnae = $this->validateCompanyData($companyData);
        if (!$hasValidCnae) {
            return response()->json([
                'errors' => true,
                'message' => 'Empresa não se possuí ao cnae necessário',
            ], 404);
        }

        return $this->createCompany($validated);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required',
            'social_name' => 'required',
            'legal_name' => 'required',
            'creation_date' => 'required|date',
            'responsible_email' => 'required|email',
            'responsible_name' => 'required',
        ]);


        if ($validator->fails()) {
            return response()->json([
                'errors' => true,
                'message' => 'Dados incompletos ou errados, por favor verifique.',
            ], 422);
        }
        $validated = $validator->validated();

        $oldCompany = Company::where('id', $id)->first(
            [
                "document_number",
                "social_name",
                "legal_name",
                "creation_date",
                "responsible_email",
                "responsible_name",
            ]
        );
        if (!$oldCompany) {
            return response()->json([
                'errors' => true,
                'message' => 'Empresa não encontrada.',
            ], 404);
        }

        if ($oldCompany->document_number != $validated["document_number"]) {
            $hasDuplicatedDocument = $this->verifyDuplicatedDocument($validated["document_number"], $id);
            if ($hasDuplicatedDocument) {
                return response()->json([
                    'errors' => true,
                    'message' => 'Já existe uma empresa registrada com o documento enviado.',
                ], 422);
            }

            $companyData = $this->getCompanyData($validated['document_number']);
            if ($companyData["type"] && $companyData["type"] == "not_found") {
                return response()->json([
                    'errors' => true,
                    'message' => 'Não foi possível validar os dados da empresa, verifique se o CNPJ enviado está correto.',
                ], 404);
            }

            $hasValidCnae = $this->validateCompanyData($companyData);
            if (!$hasValidCnae) {
                return response()->json([
                    'errors' => true,
                    'message' => 'Empresa não se possuí ao cnae necessário.',
                ], 404);
            }
        }

        return $this->updateCompany($oldCompany, $validated);
    }

    public function destroy(string $id)
    {
        $company = Company::where('id', $id)->first();
        if (!$company) {
            return response()->json([
                'errors' => true,
                'message' => 'Empresa não encontrada.',
            ], 404);
        }

        $company->delete();
        return;
    }

    private function getCompanyData(string $documentNumber)
    {
        $response = Http::acceptJson()->get("https://brasilapi.com.br/api/cnpj/v1/$documentNumber");
        return $response;
    }

    private function validateCompanyData($company)
    {
        $primary_cnae = $company["cnae_fiscal"];
        $secondary_cnae = $company["cnaes_secundarios"];

        $hasValidCnae = false;
        if ($primary_cnae == 4614100) {
            // if ($primary_cnae == 6202300) {
            $hasValidCnae = true;
        }

        foreach ($secondary_cnae as $cnae) {
            if ($cnae['codigo'] == 4614100) {
                // if ($cnae == 6202300) {
                $hasValidCnae = true;
            }
        }

        return $hasValidCnae;
    }

    private function createCompany($company)
    {
        $newCompany = new Company;

        $newCompany->document_number = $company["document_number"];
        $newCompany->social_name = $company["social_name"];
        $newCompany->legal_name = $company["legal_name"];
        $newCompany->creation_date = $company["creation_date"];
        $newCompany->responsible_email = $company["responsible_email"];
        $newCompany->responsible_name = $company["responsible_name"];
        $newCompany->save();

        return $newCompany;
    }

    private function verifyDuplicatedDocument(string $documentNumber, string $id)
    {
        $duplicatedCompany = Company::where('id', '!=', $id)
            ->andWhere('document_number', $documentNumber)
            ->first();
        return (bool) $duplicatedCompany;
    }

    private function updateCompany(Company $oldCompany, $newCompanyData)
    {
        $oldCompany->document_number = $newCompanyData["document_number"];
        $oldCompany->social_name = $newCompanyData["social_name"];
        $oldCompany->legal_name = $newCompanyData["legal_name"];
        $oldCompany->creation_date = $newCompanyData["creation_date"];
        $oldCompany->responsible_email = $newCompanyData["responsible_email"];
        $oldCompany->responsible_name = $newCompanyData["responsible_name"];
        $oldCompany->save();

        return $oldCompany;
    }

    private function buildPagination($page, $limit, $offset, $total, $result)
    {
        $firstItem = $offset + 1;
        $lastItem = ($offset + $limit) < $total ? $offset + $limit : $total;
        $totalPages = ($total / $limit) >= 1 ?  ceil($total / $limit) : 1;
        $pagination = [
            "page" => $page,
            "total_pages" => $totalPages,
            "curren_view" => "$firstItem à $lastItem de $total",
            "items" => $result
        ];

        return $pagination;
    }
}
